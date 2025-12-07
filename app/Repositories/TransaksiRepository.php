<?php


namespace App\Repositories;


use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;
use App\DTO\HistoryFilterDTO;
use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Collection;

class TransaksiRepository
{
    public function getTenantHistory(HistoryFilterDTO $dto)
    {
        $query = TransaksiDetail::selectRaw("\n tenants.nama_tenant,\n tenants.id,\n SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_1,\n SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_2,\n (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - \n (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_1,\n (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - \n (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_2\n ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai')
            ->where('tenants.id', $dto->tenant_id);


        if ($dto->filter_date) {
            $query->whereDate('transaksi.updated_at', $dto->filter_date);
        } elseif ($dto->start_date && $dto->end_date) {
            $query->whereBetween('transaksi.updated_at', [$dto->start_date, $dto->end_date]);
        }


        return $query->groupBy('tenants.id', 'tenants.nama_tenant')->get();
    }

    public function findWithUser(int $id): ?Transaksi
    {
        return Transaksi::with('user')->find($id);
    }

    public function getTenantOrders(?int $tenantId, ?string $status = null): Collection
    {
        if (!$tenantId) {
            return collect();
        }

        $query = Transaksi::with([
            'listTransaksiDetail.menus.tenants' => function ($q) use ($tenantId) {
                $q->where('id', $tenantId);
            },
            'user',
        ])
            ->whereHas('listTransaksiDetail.menus.tenants', function ($q) use ($tenantId) {
                $q->where('id', $tenantId);
            })
            ->whereNotIn('status', ['pending', 'expire', 'cancel']);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Cek apakah ada transaksi lain di multitenant yang sudah diantar oleh driver lain.
     */
    public function hasAnotherDeliveredInMultitenant(int $multitenantId, int $excludeTransaksiId): bool
    {
        return Transaksi::where('multitenant_id', $multitenantId)
            ->where('id', '!=', $excludeTransaksiId)
            ->where('status', 'diantar')
            ->whereNotNull('driver_id')
            ->exists();
    }
}
