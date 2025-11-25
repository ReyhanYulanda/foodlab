<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checkout;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Support\Facades\DB;

class UpdateFailedTransactions extends Command
{
    protected $signature = 'transactions:update-failed';
    protected $description = 'Update transaksi menjadi gagal_bayar jika checkout expired atau status_bayar failed';

    public function handle(Firebases $firebases)
    {
        // =============================
        // 1. HANDLE CHECKOUT YANG EXPIRED
        // =============================

        $expiredCheckouts = Checkout::with('transaksi')
            ->where('status_bayar', 'pending')
            ->whereNotNull('tgl_akhir_tagihan')
            ->where('tgl_akhir_tagihan', '<', now())
            ->get();

        foreach ($expiredCheckouts as $checkout) {
            DB::transaction(function () use ($checkout, $firebases) {

                $transaksi = $checkout->transaksi;

                // Update checkout → failed
                $checkout->update(['status_bayar' => 'failed']);

                // Update transaksi → gagal_bayar
                if ($transaksi && $transaksi->status === 'pending') {
                    $transaksi->update(['status' => 'gagal_bayar']);
                }

                // Update multitenant
                if ($transaksi && $transaksi->multitenant_id) {
                    Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                        ->where('status', 'pending')
                        ->update(['status' => 'gagal_bayar']);
                }

                // Kirim notifikasi ke user
                $user = User::with('fcmTokens')->find($checkout->user_id);
                $tokens = $user ? $user->fcmTokens->pluck('fcm_token')->toArray() : [];

                if (!empty($tokens)) {
                    $firebases
                        ->withNotification(
                            'Pembayaran Expired',
                            'Pembayaran untuk pesanan #' . $transaksi->id . ' telah kedaluwarsa.'
                        )
                        ->withData([
                            'title' => 'Pembayaran Expired',
                            'body' => 'Pembayaran untuk pesanan #' . $transaksi->id . ' telah kedaluwarsa.',
                        ])
                        ->sendToFallback($tokens);
                }

                $this->info("Checkout ID {$checkout->id} EXPIRED → failed & transaksi updated.");
            });
        }


        // =============================
        // 2. HANDLE CHECKOUT YANG SUDAH FAILED DARI CALLBACK
        // =============================

        $failedCheckouts = Checkout::with('transaksi', 'cashier')
            ->where('status_bayar', 'failed')
            ->get();

        foreach ($failedCheckouts as $checkout) {
            $transaksi = $checkout->transaksi;
            if ($transaksi && $transaksi->status === 'pending') {
                $transaksi->update(['status' => 'gagal_bayar']);
                $this->info("Transaksi #{$transaksi->id} diupdate ke gagal_bayar");
            }
        }

        return Command::SUCCESS;
    }
}
