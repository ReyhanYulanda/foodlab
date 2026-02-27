<?php

namespace App\Services\Transaksi\Actions;

use App\Models\FcmToken;
use App\Models\Transaksi;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PushNotificationDriverToBuyerAction
{
    public function execute(Request $request, $transaksiId)
    {
        try {
            $transaksi = Transaksi::findOrFail($transaksiId);

            $cacheKey = "driver_notif_transaksi_{$transaksiId}";
            if (Cache::has($cacheKey)) {
                return response()->json([
                    'message' => 'Tunggu sebentar sebelum mengirim notifikasi lagi.'
                ], 429);
            }
            Cache::put($cacheKey, true, now()->addSeconds(20));

            $userId = $transaksi->user_id;

            $tokens = FcmToken::where('user_id', $userId)->pluck('fcm_token')->toArray();

            if (empty($tokens)) {
                return response()->json(['message' => 'User tidak memiliki FCM token'], 404);
            }

            $title = "📢 Driver menghubungi anda";
            $body = "Driver bisa saja mengirim pesan atau memberi tahu bahwa ia sudah tiba di lokasi.";

            $firebase = new Firebases();
            $firebase->withNotification($title, $body)
                ->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToDriver($tokens);

            return response()->json([
                'message' => 'Notifikasi berhasil dikirim ke pembeli',
                'pembeli' => $userId,
                'driver' => Auth::id()
            ]);
        } catch (\Throwable $e) {
            Log::error("pushNotificationDriverToBuyer Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal mengirim notifikasi',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
