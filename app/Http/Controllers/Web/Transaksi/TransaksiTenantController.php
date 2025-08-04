<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransaksiTenantController extends Controller
{
    public function transaksiTenant(Request $request)
    {
        $this->authorize('read transaksi_tenant');

        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $perPage = $request->input('per_page', 10);

        if (!$filterDate && !$startDate && !$endDate) {
            $filterDate = Carbon::today()->toDateString();
        }

        $query = TransaksiDetail::selectRaw("
                tenants.nama_tenant,
                tenants.id,
                SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
                SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
                (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
                (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
            ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(18, 0, 0);
            $end = Carbon::parse($filterDate)->setTime(17, 59, 59);

            $query->whereBetween('transaksi.created_at', [$start, $end]);
        } elseif ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->subDay()->setTime(18, 0, 0);
            $end = Carbon::parse($endDate)->setTime(17, 59, 59);

            $query->whereBetween('transaksi.created_at', [$start, $end]);
        }

        $transaksiTenant = $query->groupBy('tenants.id', 'tenants.nama_tenant')->paginate($perPage);

        return view('pages.transaksi.tenant.index', compact('transaksiTenant'));
    }

    public function detailTransaksiTenant(Request $request, $id)
    {
        $this->authorize('read transaksi_tenant');

        $perPage = $request->input('per_page', 10);
        $searchKeyword = $request->input('search_keyword');
        $statusPemesan = $request->input('status_pemesan');
        $filterDate = $request->input('filter_date');

        $query = Transaksi::with(['user', 'driver'])
            ->whereHas('listTransaksiDetail.menus', function ($qMenu) use ($id) {
                $qMenu->where('tenant_id', $id);
            })
            ->where('status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(18, 0, 0);
            $end = Carbon::parse($filterDate)->setTime(17, 59, 59);
            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($searchKeyword) {
            $query->where(function ($q) use ($searchKeyword) {
                $q->where('id', 'like', "%{$searchKeyword}%")
                    ->orWhereHas('user', function ($qUser) use ($searchKeyword) {
                        $qUser->where('name', 'like', "%{$searchKeyword}%");
                    })
                    ->orWhereHas('driver', function ($qDriver) use ($searchKeyword) {
                        $qDriver->where('name', 'like', "%{$searchKeyword}%");
                    });
            });
        }

        if ($statusPemesan) {
            $query->where('isAntar', $statusPemesan === 'antar' ? 1 : 0);
        }

        $transaksiDetails = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return view('pages.transaksi.rincianTransaksiTenant.index', compact('transaksiDetails'));
    }


    public function getPesananByTransaksi($id)
    {
        $transaksi = Transaksi::with('listTransaksiDetail.menus')->findOrFail($id);

        $pesanan = $transaksi->listTransaksiDetail->map(function ($detail) {
            $menuNama = $detail->menus->nama ?? 'Menu Tidak Ditemukan';
            $menuHarga = $detail->harga ?? 0;
            $quantity = $detail->jumlah ?? 0;

            return [
                'nama_menu' => $menuNama,
                'jumlah' => $quantity,
                'harga' => $menuHarga,
            ];
        });

        return response()->json($pesanan);
    }

    public function exportCsv(Request $request)
    {
        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
            DATE(transaksi.created_at) as tanggal,
            tenants.nama_tenant,
            tenants.id,
            SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
            SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
            SUM(transaksi.ongkos_kirim) as total_ongkir,
            (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
            (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
        ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $start = Carbon::parse($filterDate)->subDay()->setTime(18, 0, 0);
            $end = Carbon::parse($filterDate)->setTime(17, 59, 59);
            $query->whereBetween('transaksi.created_at', [$start, $end]);
        } elseif ($startDate && $endDate) {
            $start = Carbon::parse($startDate)->subDay()->setTime(18, 0, 0);
            $end = Carbon::parse($endDate)->setTime(17, 59, 59);
            $query->whereBetween('transaksi.created_at', [$start, $end]);
        }

        $transaksiTenant = $query
            ->groupByRaw('DATE(transaksi.created_at), menus.tenant_id, tenants.nama_tenant')
            ->get();

        $fileName = "transaksi_tenant_" . date('YmdHis') . ".csv";

        $handle = fopen('php://output', 'w');

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($transaksiTenant, $handle) {
            // Header CSV
            fputcsv($handle, [
                "No",
                "Tanggal",
                "Nama Tenant",
                "Pendapatan Kotor (Pesan Antar + Ambil Sendiri)",
                "Pendapatan Bersih (Pesan Antar + Ambil Sendiri)"
            ]);

            foreach ($transaksiTenant as $index => $p) {
                // Penjumlahan kolom pendapatan kotor & bersih
                $totalKotor = $p->pendapatan_kotor_1 + $p->pendapatan_kotor_2;
                $totalBersih = $p->pendapatan_bersih_1 + $p->pendapatan_bersih_2;

                fputcsv($handle, [
                    $index + 1,
                    $p->tanggal,
                    $p->nama_tenant,
                    $totalKotor,
                    $totalBersih
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
