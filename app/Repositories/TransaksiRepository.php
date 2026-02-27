<?php


namespace App\Repositories;


use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;
use App\DTO\HistoryFilterDTO;
use App\Models\Transaksi;
use DateTimeInterface;

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

    public function getPesananByTenantAndStatus(int $tenantId, ?string $status = null)
    {
        $query = Transaksi::with([
            'listTransaksiDetail.menus.tenants' => function ($query) use ($tenantId) {
                $query->where('id', $tenantId);
            },
            'user'
        ])
            ->whereHas('listTransaksiDetail.menus.tenants', function ($query) use ($tenantId) {
                $query->where('id', $tenantId);
            })
            ->whereNotIn('status', ['pending', 'expire', 'cancel']);

        $dataPesanan = $query->get();

        if ($status) {
            $dataPesanan = $dataPesanan->where('status', $status);
        }

        return $dataPesanan;
    }

    public function findWithUserById(int $id): ?Transaksi
    {
        return Transaksi::with('user')->find($id);
    }

    public function getUserOrdersPaginated(int $userId, int $perPage, int $page)
    {
        return Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user',
            'checkout'
        ])
            ->whereHas('listTransaksiDetail.menus.tenants', function ($query) use ($userId) {
                $query->where('user_id', '!=', $userId);
            })
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getUserOrTenantOrderById(int $userId, int $orderId)
    {
        return Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user',
            'checkout'
        ])
            ->forUserOrTenant($userId)
            ->where('id', $orderId)
            ->first();
    }

    public function getAllOrdersPaginated(int $perPage, int $page)
    {
        return Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user',
            'checkout'
        ])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getTenantOrdersPaginated(int $tenantId, int $perPage, int $page, ?string $searchQuery = null)
    {
        $orderQuery = Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user'
        ])->whereHas('listTransaksiDetail.menus', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        });

        if ($searchQuery) {
            $orderQuery->where(function ($query) use ($searchQuery) {
                $query->where('kode_pemesanan', 'like', '%' . $searchQuery . '%')
                    ->orWhereHas('user', function ($q) use ($searchQuery) {
                        $q->where('name', 'like', '%' . $searchQuery . '%');
                    });
            });
        }

        return $orderQuery->orderByDesc('created_at')->paginate($perPage, ['*'], 'page', $page);
    }

    public function getDriverOrdersPaginated(int $driverId, int $perPage, int $page)
    {
        return Transaksi::with([
            'listTransaksiDetail.menus.tenants',
            'user'
        ])
            ->where('isAntar', true)
            ->where('status', '!=', 'pesanan_masuk')
            ->where('status', '!=', 'pending')
            ->where(function ($query) use ($driverId) {
                $query->whereNull('driver_id')
                    ->orWhere('driver_id', $driverId);
            })
            ->orderByRaw("FIELD(status, 'siap_diantar') DESC")
            ->orderBy('id', 'asc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getDriverLeaderboard()
    {
        return Transaksi::where('status', 'selesai')
            ->whereNotNull('driver_id')
            ->select('driver_id', DB::raw('COUNT(*) as total_transaksi'))
            ->groupBy('driver_id')
            ->orderByDesc('total_transaksi')
            ->get();
    }

    public function save(Transaksi $transaksi): void
    {
        $transaksi->save();
    }

    public function getExpiredPesananMasuk(DateTimeInterface $threshold)
    {
        return Transaksi::where('status', 'pesanan_masuk')
            ->where('updated_at', '<=', $threshold)
            ->get();
    }
}
