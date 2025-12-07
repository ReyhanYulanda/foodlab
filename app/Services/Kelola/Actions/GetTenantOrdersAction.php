<?php

namespace App\Services\Kelola\Actions;

use App\Repositories\TenantRepository;
use App\Repositories\TransaksiRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetTenantOrdersAction
{
    public function __construct(
        protected TenantRepository $tenantRepository,
        protected TransaksiRepository $transaksiRepository
    ) {}

    public function execute(int $userId, ?string $status = null): Collection
    {
        try {
            $tenant = $this->tenantRepository->findByUserId($userId);

            if (!$tenant) {
                // Sebelumnya query dengan tenant_id null -> hasilnya juga kosong
                return collect();
            }

            return $this->transaksiRepository->getPesananByTenantAndStatus($tenant->id, $status);
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            throw $th;
        }
    }
}
