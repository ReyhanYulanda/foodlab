<?php

namespace App\Services\Kelola;

use App\Services\Kelola\Actions\GetTenantOrdersAction;
use App\Services\Kelola\Actions\UpdateStatusPesananAction;
use App\Services\Kelola\Actions\UpdateStatusPesananCashierAction;
use App\Services\Firebases;
use Illuminate\Http\Request;

class TenantOrderService
{
    public function __construct(
        protected GetTenantOrdersAction $getTenantOrdersAction,
        protected UpdateStatusPesananAction $updateStatusPesananAction,
        protected UpdateStatusPesananCashierAction $updateStatusPesananCashierAction,
    ) {}

    public function getDataPesanan(int $userId, ?string $status = null)
    {
        return $this->getTenantOrdersAction->execute($userId, $status);
    }

    public function updateStatusPesanan(Request $request, Firebases $firebases, int $id)
    {
        return $this->updateStatusPesananAction->execute($request, $firebases, $id);
    }

    public function updateStatusPesananCashier(Request $request, Firebases $firebases, int $id)
    {
        return $this->updateStatusPesananCashierAction->execute($request, $firebases, $id);
    }
}
