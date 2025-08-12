<?php

namespace App\Console\Commands;

use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteSiapDiambil extends Command
{
    protected $signature = 'transaksi:auto-complete-siap-diambil';
    protected $description = 'Otomatis mengubah status siap_diambil menjadi selesai setiap jam 2 pagi';

    public function handle()
    {
        $count = 0;

        $transaksiList = Transaksi::where('status', 'siap_diambil')->get();

        foreach ($transaksiList as $transaksi) {
            $transaksi->status = 'selesai';
            $transaksi->updated_at = Carbon::now('Asia/Jakarta');
            $transaksi->save();

            if ($transaksi->metode_pembayaran != 'transfer') {
                $transaksi->listTransaksiDetail()->update(['status' => 'selesai']);
            }

            $count++;
        }

        $this->info("$count transaksi berhasil diupdate menjadi selesai.");
        Log::info("$count transaksi berhasil diupdate menjadi selesai pada " . Carbon::now('Asia/Jakarta')->toDateTimeString());
        return Command::SUCCESS;
    }
}
