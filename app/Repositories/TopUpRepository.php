<?php

namespace App\Repositories;

use App\Models\TopUp;

class TopUpRepository
{
    public function getPendingByUser(int $userId)
    {
        return TopUp::where('user_id', $userId)
            ->where('isTf', 0)
            ->whereIn('status_bayar', ['1', 'settlement'])
            ->lockForUpdate()
            ->get();
    }
}
