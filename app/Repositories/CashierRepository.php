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

    public function create(array $data): Cashier
    {
        return Cashier::create($data);
    }

    public function findWithDetails(int $id): ?Cashier
    {
        return Cashier::with('details.menu.tenant')->find($id);
    }

    public function getLatestByTenant(int $tenantId): ?Cashier
    {
        return Cashier::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function getHistoryByUser(int $userId)
    {
        return Cashier::whereHas('details.menu.tenant', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
            ->whereNotNull('order_tenant')
            ->where('status', '!=', 'gagal_bayar')
            ->with([
                'details.menu' => function ($q) {
                    $q->select('id', 'nama as nama_menu', 'harga', 'tenant_id', 'gambar');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id', 'nama_gambar');
                },
                'user:id,name',
                'checkout'
            ])
            ->orderBy('order_tenant', 'asc')
            ->get();
    }

    public function getHistoryByIdAndUser(int $id, int $userId): ?Cashier
    {
        return Cashier::where('id', $id)
            ->whereHas('details.menu.tenant', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with([
                'details.menu' => function ($q) {
                    $q->select('id', 'nama as nama_menu', 'harga', 'tenant_id');
                },
                'details.menu.tenant' => function ($q) {
                    $q->select('id', 'nama_tenant', 'user_id');
                },
                'user:id,name',
                'checkout'
            ])
            ->first();
    }

    public function update(Cashier $cashier, array $data): Cashier
    {
        $cashier->update($data);
        return $cashier;
    }

    public function delete(Cashier $cashier): void
    {
        $cashier->delete();
    }
}
