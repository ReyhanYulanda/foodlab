<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Checkout;
use Carbon\Carbon;

class ExpirePendingCheckout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkout:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark pending checkouts as gagal_bayar if expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();

        $expired = Checkout::where('status_bayar', 'pending')
            ->whereNotNull('tgl_akhir_tagihan')
            ->where('tgl_akhir_tagihan', '<', $now)
            ->update([
                'status_bayar' => 'gagal_bayar'
            ]);

        $this->info("Checkout expired updated: {$expired}");
    }
}
