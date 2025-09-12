<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checkout;
use App\Models\User;
use App\Services\Firebases;

class UpdateFailedTransactions extends Command
{
    protected $signature = 'transactions:update-failed';
    protected $description = 'Update transaksi menjadi gagal_bayar jika checkout status_bayar failed';

    public function handle(Firebases $firebases)
    {
        $checkouts = Checkout::with('transaksi')
            ->where('status_bayar', 'failed')
            ->get();

        foreach ($checkouts as $checkout) {
            $transaksi = $checkout->transaksi;

            if (!$transaksi) {
                continue;
            }

            // ⛔ skip kalau transaksi sudah pernah gagal_bayar
            if ($transaksi->status === 'gagal_bayar') {
                continue;
            }

            // ✅ update status ke gagal_bayar
            $transaksi->update([
                'status' => 'gagal_bayar'
            ]);

            $this->info("Transaksi ID {$checkout->transaksi_id} diupdate ke gagal_bayar");

            // kirim notifikasi sekali
            $fcmUser = User::with('fcmTokens')->find($checkout->user_id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if (!empty($fcmUserToken)) {
                $firebases
                    ->withNotification(
                        'Pesanan gagal dibayar',
                        'Pesanan #' . $transaksi->id . ' tidak melakukan pembayaran.'
                    )
                    ->withData([
                        'title' => 'Pesanan gagal dibayar',
                        'body' => 'Pesanan #' . $transaksi->id . ' tidak melakukan pembayaran.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])
                    ->sendToFallback($fcmUserToken);

                $this->info("Notifikasi dikirim ke User ID {$checkout->user_id}");
            }
        }

        return Command::SUCCESS;
    }
}
