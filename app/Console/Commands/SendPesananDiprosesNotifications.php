<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Transaksi\TransaksiController;
use App\Models\Pengaturan;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendPesananDiprosesNotifications extends Command
{
    protected $signature = 'notifikasi:pesanan-diproses';
    protected $description = 'Kirim notifikasi setiap 1 menit jika ada pesanan masuk';

    public function handle(Firebases $firebases)
    {
        // Ambil semua transaksi dengan status pesanan_diproses
        $transaksis = Transaksi::where('status', 'pesanan_diproses')->get();

        if ($transaksis->isEmpty()) {
            $this->info('Tidak ada pesanan diproses.');
            return Command::SUCCESS;
        }

        foreach ($transaksis as $transaksi) {
            // Hitung sudah berapa menit sejak status terakhir diupdate
            $minutes = Carbon::parse($transaksi->updated_at)->diffInMinutes(Carbon::now());
            $retries = Pengaturan::where('nama', 'retry_pesanan_diproses')->first();
            //cast retries to int
            $retry = (int) $retries->nilai;
            
            if ($retry <= 0) {
                $retry = 15;
            }

            // Kalau belum 15 menit, skip
            if ($minutes < $retry || $minutes % $retry !== 0) {
                continue;
            }

            // Ambil tenant tokens yang relevan
            $tenantTokens = \App\Models\FcmToken::whereHas('user', function ($query) use ($transaksi) {
                $query->role('tenant')
                    ->where('isOnline', 1)
                    ->whereHas('transaksis', function ($q) use ($transaksi) {
                        $q->where('id', $transaksi->id)
                            ->where('status', 'pesanan_diproses');
                    });
            })
                ->pluck('fcm_token')
                ->filter()
                ->unique()
                ->toArray();

            if (empty($tenantTokens)) {
                $this->info("Tidak ada tenant online untuk transaksi {$transaksi->id}.");
                continue;
            }

            // Kirim notifikasi
            $firebases
                ->withNotification(
                    'Pesanan Masih di proses?',
                    'Coba cek tab pesanan yang diproses.'
                )
                ->withData([
                    'title' => 'Pesanan Masih di proses?',
                    'body' => 'Coba cek tab pesanan yang diproses.',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tenantTokens);

            $this->info("Notifikasi pesanan diproses terkirim ke tenant untuk transaksi {$transaksi->id}.");
            Log::info('Notifikasi pesanan diproses terkirim pada ' . Carbon::now('Asia/Jakarta')->toDateTimeString() . ' untuk transaksi ' . $transaksi->id);
        }

        return Command::SUCCESS;
    }
}
