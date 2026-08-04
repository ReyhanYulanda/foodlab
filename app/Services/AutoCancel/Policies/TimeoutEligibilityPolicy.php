<?php

namespace App\Services\AutoCancel\Policies;

use App\Models\Pengaturan;
use Carbon\Carbon;

class TimeoutEligibilityPolicy
{
    public function getTimeoutMinutes(): int
    {
        $timeout = Pengaturan::where('nama', 'timeout_pesanan')->value('nilai');

        return $timeout ? (int) $timeout : 10;
    }

    public function getThreshold(): Carbon
    {
        return Carbon::now()->subMinutes($this->getTimeoutMinutes());
    }
}
