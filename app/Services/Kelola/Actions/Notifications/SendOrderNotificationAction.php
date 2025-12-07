<?php

namespace App\Services\Kelola\Actions\Notifications;

use App\Models\User;
use App\Services\Firebases;

class SendOrderNotificationAction
{
    public function execute($transaksi, Firebases $firebases): void
    {
        $masbroTokens = User::role('masbro')
            ->where('isOnline', 1)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $masbroOfflineTokens = User::role('masbro')
            ->where('isOnline', 0)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

        $sendToUser = function ($title, $body, $type) use ($firebases, $transaksi, $fcmUserToken) {
            if (!empty($fcmUserToken)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title'         => $title,
                        'body'          => $body,
                        'type'          => $type,
                        'transaksi_id'  => $transaksi->id,
                        'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($fcmUserToken);
            }
        };

        $sendToTenant = function ($title, $body, $type) use ($firebases, $transaksi) {
            $pemilik = optional($transaksi->tenant)->pemilik;

            if ($pemilik) {
                $tokens = $pemilik->fcmTokens()->pluck('fcm_token')->filter()->unique()->toArray();

                if (!empty($tokens)) {
                    $firebases->withNotification($title, $body)
                        ->withData([
                            'title'         => $title,
                            'body'          => $body,
                            'type'          => $type,
                            'transaksi_id'  => $transaksi->id,
                            'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToTenant($tokens);
                }
            }
        };

        $sendToDrivers = function ($title, $body, $type) use ($firebases, $transaksi, $masbroTokens) {
            if (!empty($masbroTokens)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title'         => $title,
                        'body'          => $body,
                        'type'          => $type,
                        'transaksi_id'  => $transaksi->id,
                        'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToDriver($masbroTokens);
            }
        };

        $sendToOfflineDrivers = function ($title, $body, $type) use ($firebases, $transaksi, $masbroOfflineTokens) {
            if (!empty($masbroOfflineTokens)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title'         => $title,
                        'body'          => $body,
                        'type'          => $type,
                        'transaksi_id'  => $transaksi->id,
                        'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($masbroOfflineTokens);
            }
        };

        if ($transaksi->status === 'pesanan_diproses') {
            $sendToUser(
                'Pesanan Sedang Diproses',
                "Pesanan {$transaksi->id} sedang dibuat oleh tenant. Mohon ditunggu, ya!",
                'pesanan_diproses'
            );
        }

        if ($transaksi->status === 'siap_diantar') {
            $sendToUser(
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->id} selesai dibuat. Kami sedang mencari driver untuk mengantar pesananmu",
                'siap_diantar'
            );

            $sendToDrivers(
                'Ada Pesanan Siap Diantar',
                "Pesanan {$transaksi->id} sudah siap. Yuk, ambil dan antar sekarang!",
                'siap_diantar_driver'
            );

            $sendToOfflineDrivers(
                'Ada Pesanan Siap Diantar Loh',
                "Pesanan ke {$transaksi->id}. Yuk, nyalain status drivermu!",
                'siap_diantar_driver'
            );
        }

        if ($transaksi->status === 'siap_diambil') {
            $sendToUser(
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->id} selesai dibuat. Yuk ambil pesanananmu sekarang",
                'siap_diambil'
            );
        }

        if ($transaksi->status === 'diantar') {
            $sendToUser(
                'Pesanan Sedang Diantar',
                "Pesanan {$transaksi->id} sedang diantar oleh driver. Silakan tunggu sebentar.",
                'diantar'
            );
        }

        if ($transaksi->status === 'selesai') {
            $sendToUser(
                'Pesanan Selesai',
                "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽",
                'selesai'
            );
        }

        if ($transaksi->status === 'pesanan_masuk') {
            $sendToTenant(
                'Pesanan Masuk',
                'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!',
                'pesanan_masuk'
            );
        }
    }
}
