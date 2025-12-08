<?php

namespace App\Services\AutoCancel\Actions;

use App\Models\Transaksi;
use App\Models\User;
use App\Models\Pengaturan;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\CatatVoucher;
use App\Services\Firebases;
use App\Services\AutoCancel\Helpers\FeeHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessExpiredTransaksiAction
{
    public function __construct(
        private Firebases $firebases
    ) {}

    /**
     * Proses satu transaksi yang timeout.
     */
    public function handle(Transaksi $transaksi, int $timeout): void
    {
        DB::beginTransaction();

        try {
            // Ambil user & token FCM user
            $user = $transaksi->user;
            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            // Update status pesanan timeout
            $transaksi->status = 'pesanan_ditolak';
            $transaksi->catatan_penolakan = 'Pesanan dibatalkan otomatis karena tidak direspons tenant dalam waktu ' . $timeout . ' menit.';
            $transaksi->save();

            // ===================== MULTITENANT =====================
            if ($transaksi->multitenant_id) {
                $this->processMultitenant($transaksi, $fcmUserToken);
                DB::commit();
                return;
            }

            // ===================== NON-MULTITENANT =====================
            $this->refundKoin($transaksi);
            $transaksi->status = 'refund_selesai';
            $transaksi->save();

            if (!empty($fcmUserToken)) {
                $this->firebases
                    ->withNotification('Refund Berhasil', 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan ke akun kamu.')
                    ->withData([
                        'title' => 'Refund Berhasil',
                        'body' => 'Koin dari pesanan #' . $transaksi->id . ' telah dikembalikan ke akun kamu.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])
                    ->sendToFallback($fcmUserToken);
            }

            Log::info("Transaksi #{$transaksi->id} dibatalkan otomatis setelah $timeout menit dan refund berhasil.");
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi #{$transaksi->id}: " . $e->getMessage());
        }
    }

    /**
     * Proses auto cancel untuk transaksi multitenant.
     */
    private function processMultitenant(Transaksi $transaksi, array $fcmUserToken): void
    {
        // Ubah status ke refund_selesai (tenant ini saja)
        $transaksi->status = 'refund_selesai';
        $transaksi->save();

        // Cek apakah ini tenant pertama yang cancel
        $otherRefundCount = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->where('status', 'refund_selesai')
            ->count();

        $isFirstCancel = ($otherRefundCount === 0);

        if ($isFirstCancel) {
            Log::info("⚡ [AUTO CANCEL] Transaksi #{$transaksi->id} adalah tenant pertama yang cancel pada multitenant #{$transaksi->multitenant_id}.");

            $this->handleFirstMultitenantCancel($transaksi);
        } else {
            $this->handleSubsequentMultitenantCancel($transaksi);
            Log::info("ℹ️ [AUTO CANCEL] Transaksi #{$transaksi->id} bukan tenant pertama yang cancel");
        }

        // Notifikasi ke user bahwa salah satu tenant dibatalkan
        if (!empty($fcmUserToken)) {
            $this->firebases
                ->withNotification(
                    'Pesanan Dibatalkan Otomatis',
                    'Salah satu pesanan multitenant (#' . $transaksi->id . ') dibatalkan otomatis karena tenant tidak merespons.'
                )
                ->withData([
                    'title' => 'Pesanan Dibatalkan Otomatis',
                    'body' => 'Salah satu pesanan dalam grup multitenant dibatalkan otomatis karena tenant tidak merespons.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])
                ->sendToFallback($fcmUserToken);
        }

        // Notifikasi tenant bersangkutan
        $tenant = optional($transaksi->listTransaksiDetail()->with('menus.tenants.pemilik')->first())->menus->tenants ?? null;
        if ($tenant && $tenant->pemilik) {
            $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);
            $tokens = $pemilikUser ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if (!empty($tokens)) {
                $this->firebases
                    ->withNotification(
                        'Pesanan Dibatalkan Otomatis',
                        'Pesanan multitenant #' . $transaksi->multitenant_id . ' dibatalkan karena tenant tidak merespons.'
                    )
                    ->withData([
                        'title' => 'Pesanan Dibatalkan Otomatis',
                        'body' => 'Pesanan multitenant #' . $transaksi->multitenant_id . ' dibatalkan otomatis oleh sistem.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])
                    ->sendToFallback($tokens);
            }
        }

        // Cek apakah semua transaksi multitenant sudah refund_selesai/pesanan_ditolak
        $allDone = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->whereNotIn('status', ['refund_selesai', 'pesanan_ditolak'])
            ->doesntExist();

        if ($allDone) {
            $this->refundFullMultitenant($transaksi, $fcmUserToken);
        }

        // Jika priority, kirim notifikasi ke driver/masbro
        if ($transaksi->isPriority) {
            $this->notifyPriorityCancelToDrivers($transaksi);
        }
    }

    /**
     * Handle ketika tenant ini adalah cancel pertama dalam group multitenant.
     */
    private function handleFirstMultitenantCancel(Transaksi $transaksi): void
    {
        // Cari transaksi lain dalam grup yang masih aktif (bukan refund_selesai)
        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->where('status', '!=', 'refund_selesai')
            ->first();

        if (!$related) {
            Log::info("ℹ️ [AUTO CANCEL] Tidak ada transaksi aktif lain dalam multitenant #{$transaksi->multitenant_id}");
            return;
        }

        Log::info("🔄 [AUTO CANCEL] Found related transaction #{$related->id} (status: {$related->status})");

        $cancelTx = $transaksi;    // sudah refund_selesai
        $activeTx = $related;      // masih aktif

        $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');
        $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');
        $totalItems = $activeItems + $cancelItems;

        // Hitung X sesuai logic asli
        if ($activeItems <= 10) {
            $x = ($totalItems - 10) * 500;
        } else {
            $x = ($totalItems - 10) * 500 - (($activeItems - 10) * 500);
        }
        $x = max($x, 0);

        Log::info("📊 [AUTO CANCEL] Items calculation: active={$activeItems}, cancel={$cancelItems}, total={$totalItems}, X={$x}");

        $needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);

        $cancelOngkirMulti = $cancelTx->ruangan->gedung->ongkir_multitenant ?? 0;
        $activeOngkirMulti = $activeTx->ruangan->gedung->ongkir_multitenant ?? 0; // disimpan jika perlu
        if ($transaksi->isPriority) {
            $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas_multitenant')->value('nilai') ?? 4000;
        } else {
            $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
        }
        $activeBaseOngkir = $activeTx->ruangan->gedung->ongkir ?? 0;
        $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

        if ($needSwap && $cancelTx->ongkos_kirim !== $activeTx->ongkos_kirim) {
            // SWAP ONGKIR
            $tempOngkir = $cancelTx->ongkos_kirim;
            $cancelTx->ongkos_kirim = $activeTx->ongkos_kirim;
            $activeTx->ongkos_kirim = $tempOngkir;

            Log::info("🔄 [AUTO CANCEL] SWAP: transaksi #{$cancelTx->id} ({$tempOngkir}) <-> #{$activeTx->id} ({$activeTx->ongkos_kirim})");

            // Jika totalItems > 10, kurangi X dari ongkir activeTx
            if ($totalItems > 10) {
                $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                Log::info("📉 [AUTO CANCEL] Kurangi X={$x} untuk transaksi aktif #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                $activeTx->ongkos_kirim = $newOngkir;
            }

            $cancelTx->ongkos_kirim = $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
            $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);

            if ($transaksi->isPriority) {
                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + FeeHelper::extraFee($totalItems)) - $x;
                if ($totalItems <= 10) {
                    $activeTx->total += $multitenantOngkir;
                    $activeTx->ongkos_kirim += $multitenantOngkir;
                }
            } else {
                $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + FeeHelper::extraFee($totalItems)) - $x;
            }

            $cancelTx->save();
            $activeTx->save();

            Log::info("✅ [AUTO CANCEL SWAP DONE] Swap completed for multitenant #{$transaksi->multitenant_id}");
            Log::info("   Cancel #{$cancelTx->id} ongkir: {$cancelTx->ongkos_kirim}");
            Log::info("   Active #{$activeTx->id} ongkir: {$activeTx->ongkos_kirim}");
        } else {
            Log::info("ℹ️ [AUTO CANCEL] Tidak perlu swap, cek kondisi:");
            Log::info("   - Cancel ongkir (#{$cancelTx->id}): {$cancelTx->ongkos_kirim}");
            Log::info("   - Active ongkir (#{$activeTx->id}): {$activeTx->ongkos_kirim}");
            Log::info("   - Need swap: " . ($needSwap ? 'YES' : 'NO'));

            if ($totalItems <= 10) {
                if ($transaksi->isPriority) {
                    $activeTx->total += $multitenantOngkir;
                    $activeTx->ongkos_kirim += $multitenantOngkir;
                    $activeTx->save();
                }
            }

            if ($totalItems > 10) {
                if ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim) {
                    $newOngkir = max($cancelTx->ongkos_kirim - $x, 0);
                    Log::info("📉 [AUTO CANCEL] Kurangi X={$x} dari cancel (besar) #{$cancelTx->id}: {$cancelTx->ongkos_kirim} -> {$newOngkir}");
                    $cancelTx->ongkos_kirim = $newOngkir;
                } else {
                    $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                    Log::info("📉 [AUTO CANCEL] Kurangi X={$x} dari active (besar) #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                    $activeTx->ongkos_kirim = $newOngkir;
                }

                $cancelTx->ongkos_kirim = $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + FeeHelper::extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);

                if ($transaksi->isPriority) {
                    $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + FeeHelper::extraFee($totalItems)) - $x;
                } else {
                    $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + FeeHelper::extraFee($totalItems)) - $x;
                }

                $cancelTx->save();
                $activeTx->save();
            } else {
                Log::info("ℹ️ [AUTO CANCEL] Total items ≤ 10, no X to apply");
            }
        }
    }

    /**
     * Handle ketika bukan cancel pertama di multitenant.
     */
    private function handleSubsequentMultitenantCancel(Transaksi $transaksi): void
    {
        $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->where('id', '!=', $transaksi->id)
            ->first();

        $cancelTx = $transaksi;
        $activeTx = $related;

        $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');
        $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');
        $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

        $totalItems = $activeItems + $cancelItems;

        if ($totalItems <= 10) {
            if ($transaksi->isPriority) {
                $activeTx->total -= $multitenantOngkir;
                $activeTx->ongkos_kirim -= $multitenantOngkir;
                $activeTx->save();
            }
        }
    }

    /**
     * Refund penuh untuk semua transaksi multitenant ketika sudah all done.
     */
    private function refundFullMultitenant(Transaksi $transaksi, array $fcmUserToken): void
    {
        $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $totalRefund;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $totalRefund,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
        ]);

        // Kembalikan voucher & cashback jika semua refund / auto cancel
        $transaksiDenganVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
            ->whereNotNull('voucher_id')
            ->first();

        if ($transaksiDenganVoucher && $transaksiDenganVoucher->voucher_id) {
            $voucher = $transaksiDenganVoucher->voucher;

            if ($voucher) {
                CatatVoucher::where('transaksi_id', $transaksiDenganVoucher->id)->delete();

                $voucher->increment('quantity');

                if ($voucher->cashback) {
                    $voucher->cashback->increment('quantity');
                }

                Log::info("🔁 Voucher #{$voucher->id} dikembalikan otomatis karena semua transaksi multitenant #{$transaksi->multitenant_id} refund (auto cancel).");
            }
        }

        // Notifikasi refund penuh ke user
        if (!empty($fcmUserToken)) {
            $this->firebases
                ->withNotification(
                    'Refund Multitenant Berhasil',
                    'Koin dari pesanan multitenant #' . $transaksi->multitenant_id . ' telah dikembalikan penuh ke akun kamu.'
                )
                ->withData([
                    'title' => 'Refund Multitenant Berhasil',
                    'body' => 'Koin dari pesanan multitenant #' . $transaksi->multitenant_id . ' telah dikembalikan penuh ke akun kamu.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                ])
                ->sendToFallback($fcmUserToken);
        }

        Log::info("Refund penuh multitenant #{$transaksi->multitenant_id} sebesar {$totalRefund} berhasil dilakukan.");
    }

    /**
     * Notifikasi untuk priority order ketika dibatalkan.
     */
    private function notifyPriorityCancelToDrivers(Transaksi $transaksi): void
    {
        if ($transaksi->driver_id == null) {
            $masbroTokens = User::role('masbro')
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $fcmMasbroToken = $masbroTokens;
            if (!empty($fcmMasbroToken)) {
                $this->firebases
                    ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                    ->withData([
                        'title' => 'Pesanan Prioritas',
                        'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])->sendToFallback($fcmMasbroToken);
                Log::info('Sending FCM to driver', ['tokens' => $fcmMasbroToken]);
            }
        } else {
            $masbroTokens = User::role('masbro')
                ->where('isOnline', 1)
                ->with('fcmTokens')
                ->get()
                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $fcmMasbroToken = $masbroTokens;
            if (!empty($fcmMasbroToken)) {
                $this->firebases
                    ->withNotification('Pesanan prioritas', "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}")
                    ->withData([
                        'title' => 'Pesanan Prioritas',
                        'body' => "Salah satu pesanan prioritas  dibatalkan #{$transaksi->id}",
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])->sendToDriver($fcmMasbroToken);
                Log::info('Sending FCM to driver', ['tokens' => $fcmMasbroToken]);
            }
        }
    }

    /**
     * Logic refundKoin non-multitenant (dipindah dari command).
     */
    private function refundKoin(Transaksi $transaksi): void
    {
        if ($transaksi->status === 'refund_selesai') {
            throw new \Exception("Transaksi sudah direfund sebelumnya.");
        }

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $transaksi->total,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund pesanan #' . $transaksi->id,
        ]);

        CatatVoucher::where('transaksi_id', $transaksi->id)->delete();

        if ($transaksi->multitenant_id === null) {
            if ($transaksi->cashback_amount > 0 && $transaksi->voucher_id) {
                $voucher = $transaksi->voucher;

                if ($voucher) {
                    $voucher->increment('quantity');

                    if ($voucher->cashback) {
                        $voucher->cashback->increment('quantity');
                    }

                    Log::info("Voucher #{$voucher->id} dikembalikan karena refund transaksi #{$transaksi->id}");
                }
            }
        }
    }
}
