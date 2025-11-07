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

            /**
             * =====================
             * TENTUKAN STATUS BARU
             * =====================
             */
            $newStatus = $transaksi->isAntar == 1 ? 'siap_diantar' : 'siap_diambil';

            // 🔹 Jika ini pesanan antar & punya multitenant_id
            if ($transaksi->isAntar == 1 && $transaksi->multitenant_id && $transaksi->driver_id) {
                // Ambil semua transaksi dengan multitenant_id yang sama
                $relatedTransaksis = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

                // Jika semua transaksi multitenant sudah punya driver_id
                $allHaveDriver = $relatedTransaksis->every(fn($t) => !is_null($t->driver_id));

                // Maka ubah status transaksi ini langsung ke "diantar"
                if ($allHaveDriver) {
                    $newStatus = 'diantar';
                }
            }

            // Update status transaksi ini saja
            $transaksi->update(['status' => $newStatus]);

            /**
             * =====================
             * TOKEN PEMBAGIAN
             * =====================
             */

            // Tenant
            $tenantTokens = FcmToken::whereHas('user', function ($q) use ($transaksi) {
                $q->role('tenant')
                    ->where('isOnline', 1)
                    ->whereHas('transaksis', function ($t) use ($transaksi) {
                        $t->where('id', $transaksi->id);
                    });
            })->pluck('fcm_token')->filter()->unique()->toArray();

            // Masbro (jika pesan antar)
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

            // 🔹 Tenant
            if (!empty($tenantTokens)) {
                $tenantTitle = 'Pesanan otomatis dilanjutkan';
                $tenantBody = "Pesanan diganti otomatis ke {$newStatus} oleh sistem.";

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

            // 🔹 Masbro
            if ($transaksi->isAntar == 1 && !empty($masbroTokens)) {
                $title = $newStatus === 'diantar'
                    ? 'Pesanan sedang diantar!'
                    : 'Pesanan siap diantar!';
                $body = $newStatus === 'diantar'
                    ? 'Pesananmu sedang dalam perjalanan ke pelanggan.'
                    : 'Pesananmu sudah siap dan akan segera diantar.';

                $firebases
                    ->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $newStatus,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($masbroTokens);
            }

            // 🔹 Pembeli
            if (!empty($fcmUserToken)) {
                $userTitle = match ($newStatus) {
                    'siap_diantar' => 'Pesanan siap diantar!',
                    'diantar' => 'Pesanan sedang diantar!',
                    default => 'Pesanan siap diambil!',
                };

                $userBody = match ($newStatus) {
                    'siap_diantar' => 'Pesananmu sudah siap dan akan segera diantar.',
                    'diantar' => 'Pesananmu sedang dalam perjalanan.',
                    default => 'Pesananmu sudah siap, silakan diambil di lokasi.',
                };

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
