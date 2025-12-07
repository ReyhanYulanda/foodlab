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
        protected GetTenantOrdersAction $getTenantOrders,
        protected UpdateStatusPesananAction $updateStatusPesanan,
        protected UpdateStatusPesananCashierAction $updateStatusPesananCashier,
    ) {}

    public function getDataPesanan(int $userId, ?string $status = null)
    {
        return $this->getTenantOrders->execute($userId, $status);
    }

    public function updateStatusPesanan(Request $request, Firebases $firebases, int $idPesanan)
    {
        return $this->updateStatusPesanan->execute($request, $firebases, $idPesanan);
    }

    public function updateStatusPesananCashier(Request $request, Firebases $firebases, int $idPesanan)
    {
        return $this->updateStatusPesananCashier->execute($request, $firebases, $idPesanan);
    }
}
?>
