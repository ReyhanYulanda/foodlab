<?php

namespace App\Repositories;

use App\Models\CashierDetail;

class CashierDetailRepository
{
    public function insertMany(array $details): void
    {
        if (!empty($details)) {
            CashierDetail::insert($details);
        }
    }

    public function deleteByCashierId(int $cashierId): void
    {
        CashierDetail::where('cashier_id', $cashierId)->delete();
    }
}
