<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checkout;

class UpdateFailedTransactions extends Command
{
    protected $signature = 'transactions:update-failed';
    protected $description = 'Update transaksi menjadi gagal_bayar jika checkout status_bayar failed';

    public function handle()
    {
        $checkouts = Checkout::with('transaksi')
            ->where('status_bayar', 'failed')
            ->get();

        foreach ($checkouts as $checkout) {
            if ($checkout->transaksi) {
                $checkout->transaksi->update([
                    'status' => 'gagal_bayar'
                ]);

                $this->info("Transaksi ID {$checkout->transaksi_id} diupdate ke gagal_bayar");
            }
        }

        return Command::SUCCESS;
    }
}
