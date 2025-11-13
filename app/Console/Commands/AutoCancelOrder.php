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
