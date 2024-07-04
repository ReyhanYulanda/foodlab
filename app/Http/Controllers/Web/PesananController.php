<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PesananController extends Controller
{
    public function index()
    {
        $this->authorize('read pesanan');
        $user = Auth::user();

        $tenant = Tenants::where("user_id", $user->id)->first();

        $allPesananNonDitolak = Transaksi::with([
            'listTransaksiDetail' => function ($query) use ($tenant) {
                $query->whereHas('menus', function ($menus) use ($tenant) {
                    $menus->where('tenant_id', $tenant->id);
                });
            }, 'user'
        ])
            ->where('transaksi.status', '!=', 'pesanan_ditolak')
            ->whereHas('listTransaksiDetail.menus.tenants', function ($query) use ($tenant) {
                $query->where('id', $tenant->id);
            })
            ->whereNotIn('status', ['pending', 'expire', 'cancel'])
            ->orderByDesc('created_at');
        $allPesananDitolak = Transaksi::with([
            'listTransaksiDetail' => function ($query) use ($tenant) {
                $query->whereHas('menus', function ($menus) use ($tenant) {
                    $menus->where('tenant_id', $tenant->id);
                });
            }, 'user'
        ])
            ->where('transaksi.status', 'pesanan_ditolak')
            ->whereHas('listTransaksiDetail.menus.tenants', function ($query) use ($tenant) {
                $query->where('id', $tenant->id);
            })
            ->whereNotIn('status', ['pending', 'expire', 'cancel'])
            ->orderByDesc('created_at');

        $pesananDitolak = $allPesananDitolak->paginate($perPage = 15, $columns = ['*'], $pageName = 'pesanan_ditolak_page');
        $pesananNonDitolak = $allPesananNonDitolak->paginate($perPage = 15, $columns = ['*'], $pageName = 'pesanan_non_ditolak_page');

        return view('pages.pesanan.index', compact(
            'pesananNonDitolak',
            'pesananDitolak',
            // 'pesananTotal',
            // 'pesananTotalBatal'
        ));
    }
}
