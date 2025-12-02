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
                        Log::info("ℹ️ [AUTO CANCEL] Transaksi #{$transaksi->id} bukan tenant pertama yang cancel");
                    }
                    // ========== END LOGIKA SWAP ==========

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

                    DB::commit();
                    continue;
                }

                // === NON-MULTITENANT (default) ===
                $this->refundKoin($transaksi);
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
