<?php

namespace App\Services\Kelola\Actions;

use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HandlePriorityOrderAction
{
    public function execute(Request $request, Firebases $firebases, $transaksi): void
    {
        // Blok 1: pesanan_masuk → pesanan_diproses prioritas
        if (
            $transaksi->status === 'pesanan_masuk' &&
            $request->status === 'pesanan_diproses' &&
            $transaksi->isPriority == 1
        ) {
            if ($transaksi->driver_id == null) {
                $masbroOfflineTokens = User::role('masbro')
                    ->with('fcmTokens')
                    ->get()
                    ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($masbroOfflineTokens)) {
                    $firebases
                        ->withNotification('Ada Pesanan Prioritas', 'Gasin yuk ada ongkir tambahannya loh')
                        ->withData([
                            'title' => 'Ada Pesanan Prioritas',
                            'body'  => 'Gasin yuk ada ongkir tambahannya loh',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($masbroOfflineTokens);
                }
            } else {
                $driver = User::with('fcmTokens')->find($transaksi->driver_id);
                $fcmDriverToken = $driver ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title' => 'Perubahan status pesanan prioritas',
                            'body'  => 'Cek status pesanan prioritas',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($fcmDriverToken);
                }
            }
        }

        // Blok 2: pesanan_diproses → siap_diantar (prioritas)
        if (
            $transaksi->status === 'pesanan_diproses' &&
            $request->status === 'siap_diantar' &&
            $transaksi->isPriority == 1
        ) {
            if ($transaksi->driver_id !== null) {
                // auto skip ke diantar
                $request->merge(['status' => 'diantar']);

                $driver = User::with('fcmTokens')->find($transaksi->driver_id);
                $fcmDriverToken = $driver ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title' => 'Perubahan status pesanan prioritas',
                            'body'  => 'Cek status pesanan prioritas',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToDriver($fcmDriverToken);
                }

                Log::info("Pesanan prioritas #{$transaksi->id} otomatis diubah menjadi 'diantar' karena sudah memiliki driver.");
            } else {
                Log::info("Pesanan prioritas #{$transaksi->id} masih menunggu driver, tetap di 'siap_diantar'.");
            }
        }
    }
}