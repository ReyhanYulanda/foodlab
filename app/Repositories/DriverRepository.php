<?php

namespace App\Repositories;

use App\Models\User;

class DriverRepository
{
    /**
     * Ambil token FCM untuk semua driver (role: masbro) berdasarkan status online.
     */
    public function getDriverTokens(int $onlineStatus): array
    {
        return User::role('masbro')
            ->where('isOnline', $onlineStatus)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($u) => $u->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    public function getDriverById(?int $driverId): array
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
