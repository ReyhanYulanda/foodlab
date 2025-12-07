<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\User;
use App\Repositories\DriverRepository;
use App\Services\Firebases;

class SendOrderNotificationsAction
{
    public function __construct(
        protected DriverRepository $driverRepository
    ) {}

    public function execute(Transaksi $transaksi, Firebases $firebases): void
    {
        // Driver tokens
        $masbroTokens        = $this->driverRepository->getMasbroTokensByOnlineStatus(1);
        $masbroOfflineTokens = $this->driverRepository->getMasbroTokensByOnlineStatus(0);

        // User tokens
        $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
        $fcmUserToken = $fcmUser
            ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
            : [];

        // Helper: send to user
        $sendToUser = function (string $title, string $body, string $type) use ($firebases, $transaksi, $fcmUserToken) {
            if (!empty($fcmUserToken)) {
                $firebases
                    ->withNotification($title, $body)
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

        // Helper: send to tenant (pemilik)
        $sendToTenant = function (string $title, string $body, string $type) use ($firebases, $transaksi) {
            $pemilik = optional($transaksi->tenant)->pemilik;

            if ($pemilik) {
                $tokens = $pemilik->fcmTokens()->pluck('fcm_token')->filter()->unique()->toArray();

                if (!empty($tokens)) {
                    $firebases
                        ->withNotification($title, $body)
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

        // Helper: send to drivers online
        $sendToDrivers = function (string $title, string $body, string $type) use ($firebases, $transaksi, $masbroTokens) {
            if (!empty($masbroTokens)) {
                $firebases
                    ->withNotification($title, $body)
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

        // Helper: send to drivers offline
        $sendToOfflineDrivers = function (string $title, string $body, string $type) use ($firebases, $transaksi, $masbroOfflineTokens) {
            if (!empty($masbroOfflineTokens)) {
                $firebases
                    ->withNotification($title, $body)
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

        // === LOGIKA NOTIFIKASI BERDASARKAN STATUS ===

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

            // original code: sendToDrivers di-comment, tetap dipertahankan (tidak diaktifkan)
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
