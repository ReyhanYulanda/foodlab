<?php

namespace App\Console\Commands;

use App\Models\CatatVoucher;
use Illuminate\Console\Command;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pengaturan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Firebases;

class AutoCancelOrder extends Command
{
    protected $signature = 'order:autocancel';
    protected $description = 'Batalkan otomatis pesanan_masuk setelah waktu tertentu dari pengaturan';

    public function handle(Firebases $firebases)
    {
        $timeout = Pengaturan::where('nama', 'timeout_pesanan')->value('nilai');
        $timeout = $timeout ?? 10;

        $threshold = Carbon::now()->subMinutes($timeout);

        $transaksis = Transaksi::where('status', 'pesanan_masuk')
            ->whereNull('driver_id')
            ->where('updated_at', '<=', $threshold)
            ->get();

        foreach ($transaksis as $transaksi) {
            DB::beginTransaction();
            try {
                $user = $transaksi->user;
                $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                // === Update status pesanan yang timeout ===
                $transaksi->status = 'pesanan_ditolak';
                $transaksi->catatan_penolakan = 'Pesanan dibatalkan otomatis karena tidak direspons tenant dalam waktu ' . $timeout . ' menit.';
                $transaksi->save();

                /**
                 * ========== LOGIKA MULTITENANT ==========
                 * Jika multitenant, tenant ini hanya ubah status ke refund_selesai.
                 * Refund baru dilakukan ketika SEMUA tenant dalam grup tsb sudah refund_selesai / ditolak.
                 */
                if ($transaksi->multitenant_id) {
                    $transaksi->status = 'refund_selesai';
                    $transaksi->save();

                    // ========== LOGIKA SWAP ONGKIR UNTUK TENANT PERTAMA YANG CANCEL ==========
                    // === cek apakah ini adalah tenant PERTAMA yang melakukan refund (first-cancel) ===
                    $otherRefundCount = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->where('id', '!=', $transaksi->id)
                        ->where('status', 'refund_selesai')
                        ->count();

                    $isFirstCancel = ($otherRefundCount === 0);

                    if ($transaksi->isAntar == 1) {
                        if ($isFirstCancel) {
                            Log::info("⚡ [AUTO CANCEL] Transaksi #{$transaksi->id} adalah tenant pertama yang cancel pada multitenant #{$transaksi->multitenant_id}.");

                            // Cari related transaksi lain dalam grup yang MASIH AKTIF (bukan refund)
                            $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                                ->where('id', '!=', $transaksi->id)
                                ->where('status', '!=', 'refund_selesai') // Yang belum refund
                                ->first();

                            if ($related) {
                                Log::info("🔄 [AUTO CANCEL] Found related transaction #{$related->id} (status: {$related->status})");

                                // Tentukan mana yang cancel dan mana yang tetap aktif
                                $cancelTx = $transaksi;      // status sudah refund_selesai
                                $activeTx = $related;        // status masih aktif (pesanan_masuk/diproses/dll)

                                // Hitung items
                                $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');  // items yang tetap aktif
                                $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');  // items yang dicancel

                                // Hitung X berdasarkan rumus
                                $totalItems = $activeItems + $cancelItems;

                                if ($activeItems <= 10) {
                                    // Case: activeItems ≤ 10
                                    $x = ($totalItems - 10) * 500;
                                } else {
                                    // Case: activeItems > 10  
                                    $x = ($totalItems - 10) * 500 - (($activeItems - 10) * 500);
                                }
                                $x = max($x, 0); // Pastikan X tidak negatif

                                Log::info("📊 [AUTO CANCEL] Items calculation: active={$activeItems}, cancel={$cancelItems}, total={$totalItems}, X={$x}");

                                // PERBAIKAN: Cek kondisi untuk menentukan perlu swap atau tidak
                                // Swap hanya dilakukan jika ongkir cancel > ongkir active
                                $needSwap = ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim);

                                $cancelOngkirMulti = $cancelTx->ruangan->gedung->ongkir_multitenant ?? 0;
                                $activeOngkirMulti = $activeTx->ruangan->gedung->ongkir_multitenant ?? 0;
                                if ($transaksi->isPriority) {
                                    $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas_multitenant')->value('nilai') ?? 4000;
                                } else {
                                    $activeOngkirPriority = Pengaturan::where('nama', 'ongkos_kirim_prioritas')->value('nilai') ?? 3000;
                                }
                                $activeBaseOngkir = $activeTx->ruangan->gedung->ongkir ?? 0;
                                $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

                                if ($needSwap && $cancelTx->ongkos_kirim !== $activeTx->ongkos_kirim) {
                                    // 🔄 SWAP ONGKIR: Hanya jika ongkir cancel lebih besar
                                    $tempOngkir = $cancelTx->ongkos_kirim;
                                    $cancelTx->ongkos_kirim = $activeTx->ongkos_kirim;
                                    $activeTx->ongkos_kirim = $tempOngkir;

                                    Log::info("🔄 [AUTO CANCEL] SWAP: transaksi #{$cancelTx->id} ({$tempOngkir}) <-> #{$activeTx->id} ({$activeTx->ongkos_kirim})");

                                    // Jika totalItems > 10, kurangi ongkir activeTx dengan X
                                    if ($totalItems > 10) {
                                        $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                                        Log::info("📉 [AUTO CANCEL] Kurangi X={$x} untuk transaksi aktif #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                                        $activeTx->ongkos_kirim = $newOngkir;
                                    }

                                    $cancelTx->ongkos_kirim = $cancelOngkirMulti + $this->extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                                    $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + $this->extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                                    if ($transaksi->isPriority) {
                                        $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + $this->extraFee($totalItems)) - $x;
                                        if ($totalItems <= 10) {
                                            $activeTx->total += $multitenantOngkir; //new code
                                            $activeTx->ongkos_kirim += $multitenantOngkir; //new code
                                        }
                                    } else {
                                        $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $this->extraFee($totalItems)) - $x;
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
                                            $activeTx->total += $multitenantOngkir; //new code
                                            $activeTx->ongkos_kirim += $multitenantOngkir; //new code
                                            $activeTx->save();
                                        }
                                    }

                                    // PERBAIKAN: JIKA TIDAK SWAP, tetap kurangi X dari ongkir active jika totalItems > 10
                                    if ($totalItems > 10) {
                                        // Tapi tunggu! Jika tidak swap, mungkin X perlu dikurangi dari ongkir yang lebih besar?
                                        // Sesuai case 2: ongkir besar ada di cancel (10500), kecil di active (0)
                                        // Maka kurangi X dari ongkir cancel karena dia yang lebih besar

                                        if ($cancelTx->ongkos_kirim > $activeTx->ongkos_kirim) {
                                            // Ongkir besar di cancel, kecil di active
                                            // Kurangi X dari cancel karena dialah yang lebih besar
                                            $newOngkir = max($cancelTx->ongkos_kirim - $x, 0);
                                            Log::info("📉 [AUTO CANCEL] Kurangi X={$x} dari cancel (besar) #{$cancelTx->id}: {$cancelTx->ongkos_kirim} -> {$newOngkir}");
                                            $cancelTx->ongkos_kirim = $newOngkir;
                                        } else {
                                            // Ongkir besar di active, kecil di cancel
                                            // Kurangi X dari active karena dialah yang lebih besar
                                            $newOngkir = max($activeTx->ongkos_kirim - $x, 0);
                                            Log::info("📉 [AUTO CANCEL] Kurangi X={$x} dari active (besar) #{$activeTx->id}: {$activeTx->ongkos_kirim} -> {$newOngkir}");
                                            $activeTx->ongkos_kirim = $newOngkir;
                                        }

                                        $cancelTx->ongkos_kirim = $cancelOngkirMulti + $this->extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                                        $cancelTx->total = $cancelTx->sub_total + $cancelOngkirMulti + $this->extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems);
                                        if ($transaksi->isPriority) {
                                            $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $activeOngkirPriority + $this->extraFee($totalItems)) - $x;
                                        } else {
                                            $activeTx->total = ($activeTx->sub_total + $activeBaseOngkir + $this->extraFee($totalItems)) - $x;
                                        }

                                        $cancelTx->save();
                                        $activeTx->save();
                                    } else {
                                        Log::info("ℹ️ [AUTO CANCEL] Total items ≤ 10, no X to apply");
                                    }
                                }
                            } else {
                                Log::info("ℹ️ [AUTO CANCEL] Tidak ada transaksi aktif lain dalam multitenant #{$transaksi->multitenant_id}");
                            }
                        } else {
                            $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                                ->where('id', '!=', $transaksi->id)
                                // ->where('status', '!=', 'refund_selesai') // Yang belum refund
                                ->first();
                            // Tentukan mana yang cancel dan mana yang tetap aktif
                            $cancelTx = $transaksi;      // status sudah refund_selesai
                            $activeTx = $related;        // status masih aktif (pesanan_masuk/diproses/dll)

                            // Hitung items
                            $activeItems = $activeTx->listTransaksiDetail->sum('jumlah');  // items yang tetap aktif
                            $cancelItems = $cancelTx->listTransaksiDetail->sum('jumlah');  // items yang dicancel
                            $multitenantOngkir = Pengaturan::where('nama', 'ongkos_kirim_multitenant')->value('nilai') ?? 2000;

                            $totalItems = $activeItems + $cancelItems;

                            if ($totalItems <= 10) {
                                if ($transaksi->isPriority) {
                                    $activeTx->total -= $multitenantOngkir;
                                    $activeTx->ongkos_kirim -= $multitenantOngkir;
                                    $activeTx->save();
                                }
                            }
                            Log::info("ℹ️ [AUTO CANCEL] Transaksi #{$transaksi->id} bukan tenant pertama yang cancel");
                        }
                        // ========== END LOGIKA SWAP ==========
                    }

                    // === Notifikasi ke user bahwa salah satu tenant dibatalkan ===
                    if (!empty($fcmUserToken)) {
                        $firebases
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

                    // === Kirim notifikasi ke tenant yang bersangkutan saja ===
                    $tenant = optional($transaksi->listTransaksiDetail()->with('menus.tenants.pemilik')->first())->menus->tenants ?? null;
                    if ($tenant && $tenant->pemilik) {
                        $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);
                        $tokens = $pemilikUser ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                        if (!empty($tokens)) {
                            $firebases
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

                    // === Cek apakah SEMUA transaksi multitenant sudah refund_selesai / ditolak ===
                    $allDone = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->whereNotIn('status', ['refund_selesai', 'pesanan_ditolak'])
                        ->doesntExist();

                    if ($allDone) {
                        // Semua tenant sudah refund_selesai → refund penuh ke user
                        $totalRefund = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->sum('total');

                        $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
                        $saldo->jumlah += $totalRefund;
                        $saldo->save();

                        \App\Models\TransaksiSaldoKoin::create([
                            'user_id' => $transaksi->user_id,
                            'jumlah' => $totalRefund,
                            'tipe' => 'masuk',
                            'deskripsi' => 'Refund pesanan multitenant #' . $transaksi->multitenant_id,
                        ]);

                        // ✅ Tambahan: kembalikan voucher & cashback jika semua refund / auto cancel
                        $transaksiDenganVoucher = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                            ->whereNotNull('voucher_id')
                            ->first();

                        if ($transaksiDenganVoucher && $transaksiDenganVoucher->voucher_id) {
                            $voucher = $transaksiDenganVoucher->voucher;

                            if ($voucher) {
                                // Hapus catatan penggunaan voucher
                                CatatVoucher::where('transaksi_id', $transaksiDenganVoucher->id)->delete();

                                // Kembalikan stok voucher
                                $voucher->increment('quantity');

                                // Kembalikan stok cashback (jika ada relasi)
                                if ($voucher->cashback) {
                                    $voucher->cashback->increment('quantity');
                                }

                                Log::info("🔁 Voucher #{$voucher->id} dikembalikan otomatis karena semua transaksi multitenant #{$transaksi->multitenant_id} refund (auto cancel).");
                            }
                        }

                        // Notifikasi refund penuh ke user
                        if (!empty($fcmUserToken)) {
                            $firebases
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

                    if ($transaksi->isPriority) {
                        // Kirim FCM saldo kembalian
                        if ($transaksi->driver_id == null) {
                            $masbroTokens = User::role('masbro')
                                // ->where('isOnline', 1)
                                ->with('fcmTokens')
                                ->get()
                                ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                                ->filter()
                                ->unique()
                                ->values()
                                ->toArray();

                            $fcmMasbroToken = $masbroTokens;
                            if (!empty($fcmMasbroToken)) {
                                $firebases
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
                                $firebases
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

                    DB::commit();
                    continue;
                } else {
                    // Tambahkan ke saldo koin user
                    $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
                    $saldo->jumlah += $transaksi->total;
                    $saldo->save();

                    // Catat transaksi saldo koin
                    \App\Models\TransaksiSaldoKoin::create([
                        'user_id' => $transaksi->user_id,
                        'jumlah' => $transaksi->total,
                        'tipe' => 'masuk',
                        'deskripsi' => 'Refund pesanan #' . $transaksi->id,
                    ]);

                    // Kirim notifikasi ke user
                    if (!empty($fcmUserToken)) {
                        $firebases
                            ->withNotification(
                                'Pesanan Dibatalkan',
                                "Pesanan #{$transaksi->kode_pemesanan} telah dibatalkan. Saldo sebesar Rp " . number_format($transaksi->total, 0, ',', '.') . " telah dikembalikan."
                            )
                            ->withData([
                                'title' => 'Pesanan Dibatalkan',
                                'body' => "Saldo Rp " . number_format($transaksi->total, 0, ',', '.') . " telah dikembalikan ke akun Anda.",
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ])
                            ->sendToFallback($fcmUserToken);
                    }
                }

                // === NON-MULTITENANT (default) ===
                // $this->refundKoin($transaksi);
                $transaksi->status = 'refund_selesai';
                $transaksi->save();

                if (!empty($fcmUserToken)) {
                    $firebases
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
                DB::rollback();
                Log::error("Gagal membatalkan transaksi #{$transaksi->id}: " . $e->getMessage());
            }
        }

        $this->info("Auto cancel executed with timeout $timeout minutes.");
    }

    private function extraFee($totalItems)
    {
        $extraLimit = 10;
        $costPerExtra = 500;

        // Jika current items > 10, hanya kelebihan dari 10 yang kena extra fee
        if ($totalItems > $extraLimit) {
            return ($totalItems - $extraLimit) * $costPerExtra;
        }

        return 0;
    }

    private function extraFeeRefundSalahSatu($cancelItems, $activeItems, $totalItems)
    {
        $extraLimit = 10;
        $costPerExtra = 500;

        // 20 20 (cancel)
        if ($cancelItems > $extraLimit && $activeItems > $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // gabisa
        // if ($cancelItems > $extraLimit && $activeItems > $extraLimit && $totalItems <= $extraLimit) {
        //     return ($cancelItems) * $costPerExtra;
        // }

        // gabisa
        // if ($cancelItems > $extraLimit && $activeItems <= $extraLimit && $totalItems <= $extraLimit) {
        //     return ($cancelItems) * $costPerExtra;
        // }

        // 3 (7 cancel)
        // if ($cancelItems <= $extraLimit && $activeItems <= $extraLimit && $totalItems <= $extraLimit) {
        //     return ($cancelItems) * $costPerExtra;
        // }

        // 12 (7 cancel)
        if ($cancelItems <= $extraLimit && $activeItems > $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // 7 (7 cancel)
        if ($cancelItems <= $extraLimit && $activeItems <= $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // 7 (12 cancel)
        if ($cancelItems > $extraLimit && $activeItems <= $extraLimit && $totalItems > $extraLimit) {
            return (($activeItems + $cancelItems) - $extraLimit) * $costPerExtra;
        }

        // gabisa
        // if ($cancelItems <= $extraLimit && $activeItems > $extraLimit && $totalItems <= $extraLimit) {
        //     return (($activeItems + $cancelItems) - $extraLimit) * $costPerExtra;
        // }        

        return 0;
    }

    private function refundKoin(Transaksi $transaksi)
    {
        if ($transaksi->status === 'refund_selesai') {
            throw new \Exception("Transaksi sudah direfund sebelumnya.");
        }

        $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        \App\Models\TransaksiSaldoKoin::create([
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

    private function refundKoinMultitenant(Transaksi $transaksi, $jumlah)
    {
        $saldo = \App\Models\SaldoKoin::firstOrCreate(['user_id' => $transaksi->user_id]);
        $saldo->jumlah += $jumlah;
        $saldo->save();

        \App\Models\TransaksiSaldoKoin::create([
            'user_id' => $transaksi->user_id,
            'jumlah' => $jumlah,
            'tipe' => 'masuk',
            'deskripsi' => 'Refund multitenant pesanan #' . $transaksi->id,
        ]);
    }
}
