<?php

namespace App\Actions;

use App\Models\Cashier;

class UpdateCashierStatusAction
{
    /**
     * Update status cashier.
     */
    public function execute(Cashier $cashier, string $newStatus): Cashier
    {
        $cashier->status = $newStatus;
        $cashier->save();

        return $cashier;
    }
}
