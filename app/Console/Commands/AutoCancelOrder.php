<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoCancelOrder extends Command
{
    protected $signature = 'order:autocancel';
    protected $description = 'Batalkan otomatis pesanan_masuk setelah 10 menit';

    public function handle()
    {
        $threshold = Carbon::now()->subMinutes(10);

        $transaksis = Transaksi::where('status', 'pesanan_masuk')
            ->where('created_at', '<=', $threshold)
            ->get();

        foreach ($transaksis as $transaksi) {
            DB::beginTransaction();
            try {
                $transaksi->status = 'dibatalkan';
                $transaksi->save();

                $transaksi->refundKoin(); // jika ada logika refund
                DB::commit();
                Log::info("Transaksi #{$transaksi->id} dibatalkan otomatis karena timeout.");
            } catch (\Throwable $e) {
                DB::rollback();
                Log::error("Gagal membatalkan transaksi #{$transaksi->id}: " . $e->getMessage());
            }
        }

        $this->info('Auto cancel checked successfully.');
    }

}