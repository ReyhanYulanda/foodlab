<?php

namespace App\Services\AutoCancel\Helpers;

class FeeHelper
{
    public static function extraFee(int $totalItems): int
    {
        $extraLimit = 10;
        $costPerExtra = 500;

        if ($totalItems > $extraLimit) {
            return ($totalItems - $extraLimit) * $costPerExtra;
        }

        return 0;
    }

    public static function extraFeeRefundSalahSatu(
        int $cancelItems,
        int $activeItems,
        int $totalItems
    ): int {
        $extraLimit = 10;
        $costPerExtra = 500;

        // 20 20 (cancel)
        if ($cancelItems > $extraLimit && $activeItems > $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // 12 (7 cancel)
        if ($cancelItems <= $extraLimit && $activeItems > $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // 7 (7 cancel)
        if ($cancelItems <= $extraLimit && $activeItems <= $extraLimit && $totalItems > $extraLimit) {
            return ($cancelItems) * $costPerExtra;
        }

        // 7 (12 cancel)
        if ($cancelItems > $extraLimit && $activeItems <= $extraLimit && $totalItems > $extraLimit) {
            return (($activeItems + $cancelItems) - $extraLimit) * $costPerExtra;
        }

        return 0;
    }
}
