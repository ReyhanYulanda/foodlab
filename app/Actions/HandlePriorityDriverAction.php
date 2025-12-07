<?php

namespace App\Actions;

use App\DTO\OrderStatusUpdateDto;
use App\Models\Transaksi;
use App\Repositories\DriverRepository;
use App\Repositories\TransaksiRepository;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class HandlePriorityDriverAction
{
    public function __construct(
        protected DriverRepository $driverRepository,
        protected TransaksiRepository $transaksiRepository,
    ) {}

    /**
     * Menangani logic pesanan prioritas + multitenant skip ke "diantar".
     * Bisa mengubah dto->status (misal dari "siap_diantar" → "diantar").
     */
    public function execute(Transaksi $transaksi, OrderStatusUpdateDto $dto, Firebases $firebases): OrderStatusUpdateDto
    {
        // 1. Pesanan masuk → pesanan_diproses (priority)
        if (
            $transaksi->status === 'pesanan_masuk' &&
            $dto->status === 'pesanan_diproses' &&
            (int) $transaksi->isPriority === 1
        ) {
            if ($transaksi->driver_id === null) {
                // kirim notif ke semua driver offline
                $masbroOfflineTokens = $this->driverRepository->getMasbroTokensByOnlineStatus(0);

                if (!empty($masbroOfflineTokens)) {
                    $firebases
                        ->withNotification('Ada Pesanan Prioritas', 'Gasin yuk ada ongkir tambahannya loh')
                        ->withData([
                            'title'         => 'Ada Pesanan Prioritas',
                            'body'          => 'Gasin yuk ada ongkir tambahannya loh',
                            'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($masbroOfflineTokens);
                }
            } else {
                // kirim notif ke driver spesifik
                $fcmDriverToken = $this->driverRepository->getMasbroTokensByDriverId($transaksi->driver_id);

                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title'         => 'Perubahan status pesanan prioritas',
                            'body'          => 'Cek status pesanan prioritas',
                            'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($fcmDriverToken);
                }
            }
        }

        // 2. pesanan_diproses → siap_diantar (priority) tapi sudah ada driver → auto "diantar"
        if (
            $transaksi->status === 'pesanan_diproses' &&
            $dto->status === 'siap_diantar' &&
            (int) $transaksi->isPriority === 1
        ) {
            if ($transaksi->driver_id !== null) {
                $dto->status = 'diantar';

                // kirim notif ke driver spesifik
                $fcmDriverToken = $this->driverRepository->getMasbroTokensByDriverId($transaksi->driver_id);

                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title'         => 'Perubahan status pesanan prioritas',
                            'body'          => 'Cek status pesanan prioritas',
                            'click_action'  => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($fcmDriverToken);
                }

                Log::info("Pesanan prioritas #{$transaksi->id} otomatis diubah menjadi 'diantar' karena sudah memiliki driver.");
            } else {
                Log::info("Pesanan prioritas #{$transaksi->id} masih menunggu driver, tetap di 'siap_diantar'.");
            }
        }

        // 3. Multitenant: pesanan_diproses → siap_diantar tapi sudah ada pesanan lain diantar → skip ke "diantar"
        if (
            $transaksi->multitenant_id &&
            $transaksi->status === 'pesanan_diproses' &&
            $dto->status === 'siap_diantar' &&
            $transaksi->driver_id !== null
        ) {
            $hasDelivered = $this->transaksiRepository->hasAnotherDeliveredInMultitenant(
                $transaksi->multitenant_id,
                $transaksi->id
            );

            if ($hasDelivered) {
                $dto->status = 'diantar';
                Log::info("Multitenant {$transaksi->multitenant_id} otomatis skip ke 'diantar' karena sudah ada pesanan lain yang diantar.");
            }
        }

        return $dto;
    }
}
