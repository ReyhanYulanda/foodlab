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

        $notifiedUsers = [];

        foreach ($checkouts as $checkout) {
            $user = $checkout->user;

            if ($checkout->transaksi) {
                $checkout->transaksi->update([
                    'status' => 'gagal_bayar'
                ]);

                $this->info("Transaksi ID {$checkout->transaksi_id} diupdate ke gagal_bayar");

                if ($user) {
                    $notifiedUsers[$user->id] = $checkout->transaksi->id;
                    // simpan user + transaksi terakhir yg gagal
                }
            }
        }

        // kirim notifikasi sekali per user
        foreach ($notifiedUsers as $userId => $transaksiId) {
            $fcmUser = User::with('fcmTokens')->find($userId);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if (!empty($fcmUserToken)) {
                $firebases
                    ->withNotification(
                        'Pesanan gagal dibayar',
                        'Pesanan ' . $transaksiId . ' tidak melakukan pembayaran.'
                    )
                    ->withData([
                        'title' => 'Pesanan gagal dibayar',
                        'body' => 'Pesanan ' . $transaksiId . ' tidak melakukan pembayaran.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])
                    ->sendToFallback($fcmUserToken);

                $this->info("Notifikasi dikirim ke User ID {$userId}");
            }
        }

        return Command::SUCCESS;
    }
}
