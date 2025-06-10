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

        // Jika tidak ada filter apapun, set default ke hari ini
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

        // Terapkan filter
        if ($filterDate) {
            $query->whereDate('transaksi.created_at', $filterDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('transaksi.created_at', [$startDate, $endDate]);
        }

        $transaksiTenant = $query->groupBy('tenants.id', 'tenants.nama_tenant')->paginate(10);

        return view('pages.transaksi.tenant.index', compact('transaksiTenant'));
    }

    public function detailTransaksiTenant(Request $request, $id)
    {
        $this->authorize('read transaksi_tenant');

        $perPage = $request->input('per_page', 10);
        $searchTanggal = $request->input('search_tanggal');
        $searchWaktu = $request->input('search_waktu');
        $searchKeyword = $request->input('search_keyword');
        $statusPemesan = $request->input('status_pemesan');

        $query = Transaksi::with(['user', 'driver']);

        $query->whereHas('listTransaksiDetail.menus', function ($qMenu) use ($id) {
            $qMenu->where('tenant_id', $id);
        });

        $query->where('status', 'selesai');

        if ($searchTanggal) {
            $query->whereDate('created_at', $searchTanggal);
        }

        if ($searchWaktu) {
            $query->whereTime('created_at', $searchWaktu);
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
            if ($statusPemesan === 'antar') {
                $query->where('isAntar', 1);
            } elseif ($statusPemesan === 'sendiri') {
                $query->where('isAntar', 0);
            }
        }

        $transaksiDetails = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return view('pages.transaksi.rincianTransaksiTenant.index', compact('transaksiDetails'));
    }

    public function getPesananByTransaksi($id)
    {
        $transaksi = Transaksi::with('listTransaksiDetail.menus')->findOrFail($id);

        $pesanan = $transaksi->listTransaksiDetail->map(function ($detail) {
            $menuNama = $detail->menus->nama ?? 'Menu Tidak Ditemukan';
            $menuHarga = $detail->menus->harga ?? 0;
            $quantity = $detail->qty ?? 0; 
            $totalHargaItem = $menuHarga * $quantity;

            return [
                'nama_menu' => $menuNama,
                'jumlah' => $quantity,
                'harga' => $totalHargaItem,
            ];
        });

        return response()->json($pesanan);
    }

    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
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
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id');

        if ($startDate && $endDate) {
            $query->whereBetween('transaksi.created_at', [$startDate, $endDate]);
        }

        $transaksiTenant = $query->groupBy('menus.tenant_id', 'tenants.nama_tenant')->get();

        $fileName = "transaksi_tenant_" . date('YmdHis') . ".csv";

        $handle = fopen('php://output', 'w');

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($transaksiTenant, $handle) {
            fputcsv($handle, ["No", "Nama Tenant", "Pendapatan Kotor (Pesan Antar)", "Ongkir", "Pendapatan Bersih (Pesan Antar)", "Pendapatan Kotor (Ambil Sendiri)", "Pendapatan Bersih (Ambil Sendiri)"]);

            foreach ($transaksiTenant as $index => $p) {
                fputcsv($handle, [
                    $index + 1,
                    $p->nama_tenant,
                    $p->pendapatan_kotor_1,
                    $p->total_ongkir,
                    $p->pendapatan_bersih_1,
                    $p->pendapatan_kotor_2,
                    $p->pendapatan_bersih_2
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
