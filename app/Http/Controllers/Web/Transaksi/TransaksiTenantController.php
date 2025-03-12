<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Http\Request;

class TransaksiTenantController extends Controller
{
    public function index()
    {
        $pendapatan = TransaksiDetail::selectRaw("
        tenants.nama_tenant,
        SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_1,
        SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) as pendapatan_kotor_2,
        SUM(transaksi.ongkos_kirim) as total_ongkir,
        (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_1,
        (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END) - (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga ELSE 0 END))) as pendapatan_bersih_2
    ")
    ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
    ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
    ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
    ->groupBy('menus.tenant_id', 'tenants.nama_tenant')
    ->get();

        return view('pages.transaksi.tenant.index', compact('pendapatan'));
    }
}
