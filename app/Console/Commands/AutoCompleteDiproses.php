<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pengaturan;
use App\Models\Transaksi;
use App\Models\FcmToken;
use App\Models\User;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoCompleteDiproses extends Command
{
    protected $signature = 'auto:complete-diproses';
    protected $description = 'Cek transaksi pesanan_diproses dan ubah status otomatis setelah 15 menit sesuai isAntar';

    public function handle(Firebases $firebases)
    {
        $transaksis = Transaksi::where('status', 'pesanan_diproses')->get();

        if ($transaksis->isEmpty()) {
            $this->info('Tidak ada pesanan dengan status pesanan_diproses.');
            return Command::SUCCESS;
        }

        $retries = Pengaturan::where('nama', 'retry_pesanan_diproses')->first();
        $retry = (int) ($retries->nilai ?? 15);
        if ($retry <= 0) $retry = 15;

        foreach ($transaksis as $transaksi) {
            $minutes = Carbon::parse($transaksi->updated_at)->diffInMinutes(Carbon::now());

            if ($minutes < $retry) continue;

            // Tentukan status baru
            $newStatus = $transaksi->isAntar == 1 ? 'siap_diantar' : 'siap_diambil';
            $transaksi->update(['status' => $newStatus]);

            /**
             * =====================
             * TOKEN PEMBAGIAN
             * =====================
             */

            // Tenant (yang online & punya transaksi ini)
            $tenantTokens = FcmToken::whereHas('user', function ($q) use ($transaksi) {
                $q->role('tenant')
                    ->where('isOnline', 1)
                    ->whereHas('transaksis', function ($t) use ($transaksi) {
                        $t->where('id', $transaksi->id);
                    });
            })->pluck('fcm_token')->filter()->unique()->toArray();

            // Masbro (jika isAntar == 1)
            $masbroTokens = [];
            if ($transaksi->isAntar == 1) {
                $masbroTokens = FcmToken::whereHas('user', function ($q) {
                    $q->role('masbro')->where('isOnline', 1);
                })->pluck('fcm_token')->filter()->unique()->toArray();
            }

            // Pembeli
            $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
            $fcmUserToken = $fcmUser
                ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                : [];

            /**
             * =====================
             * KIRIM NOTIF
             * =====================
             */

            // 🔹 Notifikasi untuk Tenant
            if (!empty($tenantTokens)) {
                $tenantTitle = 'Pesanan otomatis dilanjutkan';
                $tenantBody = "Pesanan diproses otomatis diganti ke {$newStatus} oleh sistem.";

                $firebases
                    ->withNotification($tenantTitle, $tenantBody)
                    ->withData([
                        'title' => $tenantTitle,
                        'body' => $tenantBody,
                        'type' => $newStatus,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($tenantTokens);
            }

            // 🔹 Notifikasi untuk Masbro (jika pesan antar)
            if ($transaksi->isAntar == 1 && !empty($masbroTokens)) {
                $firebases
                    ->withNotification('Pesanan siap diantar!', 'Pesananmu sudah siap dan akan segera diantar.')
                    ->withData([
                        'title' => 'Pesanan siap diantar!',
                        'body' => 'Pesananmu sudah siap dan akan segera diantar.',
                        'type' => 'siap_diantar',
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($masbroTokens);
            }

            // 🔹 Notifikasi untuk Pembeli
            if (!empty($fcmUserToken)) {
                $userTitle = $transaksi->isAntar
                    ? 'Pesanan siap diantar!'
                    : 'Pesanan siap diambil!';
                $userBody = $transaksi->isAntar
                    ? 'Pesananmu sudah siap dan akan segera diantar.'
                    : 'Pesananmu sudah siap, silakan diambil di lokasi.';

                $firebases
                    ->withNotification($userTitle, $userBody)
                    ->withData([
                        'title' => $userTitle,
                        'body' => $userBody,
                        'type' => $newStatus,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($fcmUserToken);
            }

            /**
             * =====================
             * LOG
             * =====================
             */
            $this->info("✅ Transaksi {$transaksi->id} diupdate ke {$newStatus}, notifikasi dikirim (tenant, pembeli" . ($transaksi->isAntar ? ", masbro" : "") . ").");
            Log::info("Transaksi {$transaksi->id} diupdate ke {$newStatus} oleh sistem pada " . Carbon::now('Asia/Jakarta'));
        }

        return Command::SUCCESS;
    }
}
