<?php

namespace App\Services\Cashier\Helpers;

use App\Repositories\CashierRepository;

class OrderTenantGenerator
{
    protected CashierRepository $cashierRepo;

    public function __construct(CashierRepository $cashierRepo)
    {
        $this->cashierRepo = $cashierRepo;
    }

    public function generate(int $tenantId): int
    {
        // Ambil data paling akhir untuk tenant ini
        $last = $this->cashierRepo->getLatestByTenant($tenantId);

        // Jika belum ada data sama sekali → mulai dari 1
        if (!$last) {
            return 1;
        }

        // Kalau latest created_at bukan hari ini → reset
        if ($last->created_at->isSameDay(now()) === false) {
            return 1;
        }

        // Kalau masih hari yg sama → lanjutkan
        return $last->order_tenant + 1;
    }
}
