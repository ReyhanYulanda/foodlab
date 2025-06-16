<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use Carbon\Carbon;

class StatusPesananTransaksiTenantController extends Controller
{
    public function StatusPesananTransaksi(Request $request)
    {
        $filterDate = $request->filter_date ?? Carbon::today()->toDateString();

        $statusTransaksi = Transaksi::with([
            'user',
            'listTransaksiDetail.menus.tenants',
            'driver'
        ])
        ->select('id', 'status', 'user_id', 'updated_at', 'driver_id', 'isAntar')
        ->when($filterDate, function ($query) use ($filterDate) {
            $query->whereDate('updated_at', $filterDate);
        })
        ->when($request->start_date && $request->end_date, function ($query) use ($request) {
            $query->whereBetween('updated_at', [$request->start_date, $request->end_date]);
        })
        ->when($request->search, function ($query) use ($request) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%$search%")
                ->orWhereHas('user', function ($q2) use ($search) {
                    $q2->where('name', 'like', "%$search%");
                })
                ->orWhereHas('listTransaksiDetail', function ($q) use ($search) {
                    $q->whereHas('menus', function ($q2) use ($search) {
                        $q2->whereHas('tenants', function ($q3) use ($search) {
                            $q3->where('nama', 'like', "%$search%");
                        });
                    });
                })
                ->orWhereHas('driver', function ($q4) use ($search) {
                    $q4->where('name', 'like', "%$search%");
                });
            });
        })
        ->when($request->status, function ($query) use ($request) {
            $query->where('status', $request->status);
        })
        ->when(in_array($request->isAntar, ['0', '1'], true), function ($query) use ($request) {
            $query->where('isAntar', (int) $request->isAntar);
        })
        ->paginate($request->input('per_page', 10));

        return view('pages.transaksi.statusPesananTransaksi.index', compact('statusTransaksi'));
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
}
