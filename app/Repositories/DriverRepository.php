<?php

namespace App\Repositories;

use App\Models\User;

class DriverRepository
{
    /**
     * Ambil token FCM untuk semua driver (role: masbro) berdasarkan status online.
     */
    public function getMasbroTokensByOnlineStatus(int $isOnline): array
    {
        return User::role('masbro')
            ->where('isOnline', $isOnline)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Ambil token FCM untuk driver tertentu (berdasarkan driver_id).
     */
    public function getMasbroTokensByDriverId(?int $driverId): array
    {
        if (!$driverId) {
            return [];
        }

        $driver = User::with('fcmTokens')->find($driverId);

        return $driver
            ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
            : [];
    }
}
