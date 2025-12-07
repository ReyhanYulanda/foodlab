<?php


namespace App\Repositories;


use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;
use App\DTO\HistoryFilterDTO;
use App\Models\Transaksi;

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

    public function save(Transaksi $transaksi): void
    {
        $transaksi->save();
    }
}
