<?php

namespace App\Console\Commands;

use App\Http\Controllers\Transaksi\TransaksiController;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendPesananMasukNotifications extends Command
{
    protected $signature = 'notifikasi:pesanan-masuk';
    protected $description = 'Kirim notifikasi setiap 1 menit jika ada pesanan masuk';

    public function handle(Firebases $firebases)
    {
        // Cek apakah ada transaksi dengan status pesanan_masuk
        $transaksi = Transaksi::where('status', 'pesanan_masuk')->exists();

        if (!$transaksi) {
            $this->info('Tidak ada pesanan masuk.');
            return Command::SUCCESS;
        }

        $tenantTokens = \App\Models\FcmToken::whereHas('user', function ($query) {
            $query->role('tenant')
                ->where('isOnline', 1)
                ->whereHas('transaksis', function ($q) {
                    $q->where('status', 'pesanan_masuk');
                });
        })
            ->pluck('fcm_token')
            ->filter()
            ->unique()
            ->toArray();

        if (empty($tenantTokens)) {
            $this->info('Tidak ada tenant online dengan pesanan masuk.');
            return Command::SUCCESS;
        }
        // Kirim notifikasi
        $firebases
            ->withNotification(
                'Pesanan Masuk',
                'Ada pesanan baru yang masuk! Silakan cek aplikasi untuk detailnya.'
            )
            ->withData([
                'title' => 'Pesanan Masuk',
                'body' => 'Ada pesanan baru yang masuk! Silakan cek aplikasi untuk detailnya.',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ])
            ->sendToTenant($tenantTokens);
        $this->info('Notifikasi pesanan masuk terkirim ke tenant.');
        Log::info('Notifikasi pesanan masuk terkirim pada ' . Carbon::now('Asia/Jakarta')->toDateTimeString() . ' dengan ' . count($tenantTokens) . ' tenant online.');
    }
}
