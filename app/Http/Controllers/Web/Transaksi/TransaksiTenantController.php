<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiTenantController extends Controller
{
    public function transaksiTenant(Request $request)
    {
        $this->authorize('read transaksi_tenant');

        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
                tenants.nama_tenant,
                tenants.id,
                SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_1,
                SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_2,
                (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_1,
                (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_2
            ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai');

        if ($filterDate) {
            $query->whereDate('transaksi.created_at', $filterDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('transaksi.created_at', [$startDate, $endDate]);
        }

        $transaksiTenant = $query->groupBy('tenants.id', 'tenants.nama_tenant')->paginate(10);

        return view('pages.transaksi.tenant.index', compact('transaksiTenant'));
    }

    public function detailTransaksiTenant($id)
    {
        $this->authorize('read transaksi_tenant');
        
        $transaksiDetails = Transaksi::whereHas('listTransaksiDetail.menus', function ($query) use ($id) {
            $query->where('tenant_id', $id)
            ->where('transaksi.status', 'selesai');;
        })
        ->with([
            'user',
            'driver',
        ])
        ->paginate(10);
    
        return view('pages.transaksi.rincianTransaksiTenant.index', compact('transaksiDetails'));
    } 
    
    public function getPesananByTransaksi($id)
    {
        $transaksi = Transaksi::with('listTransaksiDetail.menus')->findOrFail($id);

        $pesanan = $transaksi->listTransaksiDetail->map(function ($detail) {
            return [
                'nama_menu' => $detail->menus->nama ?? 'Menu Tidak Ditemukan',
                'jumlah' => $detail->jumlah,
                'harga' => $detail->harga,
            ];
        });

        return response()->json($pesanan);
    }


    public function exportCsv(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Query data transaksi
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

        // Tambahkan filter tanggal jika ada
        if ($startDate && $endDate) {
            $query->whereBetween('transaksi.created_at', [$startDate, $endDate]);
        }

        $transaksiTenant = $query->groupBy('menus.tenant_id', 'tenants.nama_tenant')->get();

        // Nama file CSV
        $fileName = "transaksi_tenant_" . date('YmdHis') . ".csv";

        // Membuka output stream untuk CSV
        $handle = fopen('php://output', 'w');

        // Set header untuk CSV
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$fileName",
            "Pragma" => "no-cache",
            "Expires" => "0"
        ];

        return response()->stream(function () use ($transaksiTenant, $handle) {
            // Tulis header CSV
            fputcsv($handle, ["No", "Nama Tenant", "Pendapatan Kotor (Pesan Antar)", "Ongkir", "Pendapatan Bersih (Pesan Antar)", "Pendapatan Kotor (Ambil Sendiri)", "Pendapatan Bersih (Ambil Sendiri)"]);

            // Tulis data transaksi ke CSV
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
