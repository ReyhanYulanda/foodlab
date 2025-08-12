<?php

namespace App\Console\Commands;

use App\Http\Controllers\Transaksi\TransaksiController;
use App\Models\Transaksi;
use App\Models\User;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendSiapDiantarNotifications extends Command
{
    protected $signature = 'notifikasi:siap-diantar';
    protected $description = 'Kirim notifikasi setiap 1 menit jika ada pesanan siap diantar';

    public function handle(Firebases $firebases)
    {
        // Cek apakah ada transaksi dengan status siap_diantar
        $transaksi = Transaksi::where('status', 'siap_diantar')->exists();

        if (!$transaksi) {
            $this->info('Tidak ada pesanan siap diantar.');
            return Command::SUCCESS;
        }

        // Ambil token driver yang online
        $tokens = User::role('masbro')
            ->where('isOnline', 1)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($tokens)) {
            $this->info('Tidak ada driver online.');
            return Command::SUCCESS;
        }

        // Kirim notifikasi
        $firebases
            ->withNotification(
                'Ada Pesanan Siap Diantar',
                'Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!'
            )
            ->withData([
                'title' => 'Ada Pesanan Siap Diantar',
                'body' => 'Ada pesanan siap diantar! Yuk, ambil dan antar sekarang!',
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            ])
            ->sendToDriver($tokens);

        $this->info('Notifikasi terkirim ke driver.');
        Log::info('Notifikasi siap diantar terkirim pada ' . Carbon::now('Asia/Jakarta')->toDateTimeString());
        return Command::SUCCESS;
    }
}
