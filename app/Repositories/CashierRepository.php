<?php

namespace App\Repositories;

use App\Models\Cashier;

class CashierRepository
{
    public function findWithTenantAndOwner(int $id): ?Cashier
    {
        return Cashier::with('tenant.pemilik')->find($id);
    }

    public function save(Cashier $cashier): void
    {
        $cashier->save();
    }
}
