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
            $user = $checkout->user;
            $fcmUser = User::with('fcmTokens')->find($checkout->user_id);
            $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if ($checkout->transaksi) {
                $checkout->transaksi->update([
                    'status' => 'gagal_bayar'
                ]);

                $this->info("Transaksi ID {$checkout->transaksi_id} diupdate ke gagal_bayar");
            }

            if ($user && $user->fcm_token) {
                $firebases
                    ->withNotification(
                        'Pesanan gagal dibayar',
                        'Pesanan #' . $checkout->transaksi->id . ' tidak melakukan pembayaran.'
                    )
                    ->withData([
                        'title' => 'Pesanan gagal dibayar',
                        'body' => 'Pesanan #' . $checkout->transaksi->id . ' tidak melakukan pembayaran.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])->sendToFallback($fcmUserToken);
            }
        }

        return Command::SUCCESS;
    }
}
