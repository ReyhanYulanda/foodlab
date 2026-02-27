<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\TransaksiRepository;
use App\Repositories\TenantRepository;
use Illuminate\Http\Request;
use App\Models\User;

class GetOrderTenantAction
{
    protected $transaksiRepo;
    protected $tenantRepo;

    public function __construct(TransaksiRepository $transaksiRepo, TenantRepository $tenantRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
        $this->tenantRepo = $tenantRepo;
    }

    public function execute(Request $request, User $user)
    {
        $tenant = $this->tenantRepo->getByUser($user->id);

        if (!$tenant) {
            throw new \Exception('Tenant tidak ditemukan', 404);
        }

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $searchQuery = $request->input('search');

        return $this->transaksiRepo->getTenantOrdersPaginated($tenant->id, $perPage, $page, $searchQuery);
    }
}
