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
        $this->authorize('read status_pesanan');

        $statusTransaksi = Transaksi::with([
            'user',
            'listTransaksiDetail.menus.tenants',
            'driver'
        ])
            ->select('id', 'status', 'user_id', 'updated_at', 'driver_id', 'isAntar', 'ruangan_id', 'catatan_lokasi_pengantaran', 'catatan_penolakan')
            ->when($request->start_date && $request->end_date, function ($query) use ($request) {
                $query->whereBetween('updated_at', [$request->start_date, $request->end_date]);
            }, function ($query) use ($request) {
                if ($request->filter_date) {
                    $query->whereDate('updated_at', $request->filter_date);
                }
            })
            ->when($request->search, function ($query) use ($request) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%$search%")
                        ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', "%$search%"))
                        ->orWhereHas(
                            'listTransaksiDetail',
                            fn($q) =>
                            $q->whereHas(
                                'menus',
                                fn($q2) =>
                                $q2->whereHas(
                                    'tenants',
                                    fn($q3) =>
                                    $q3->where('nama', 'like', "%$search%")
                                )
                            )
                        )
                        ->orWhereHas('driver', fn($q4) => $q4->where('name', 'like', "%$search%"));
                });
            })
            ->when($request->status, fn($query) => $query->where('status', $request->status))
            ->when(in_array($request->isAntar, ['0', '1'], true), fn($query) => $query->where('isAntar', (int)$request->isAntar))
            ->orderBy('updated_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return view('pages.transaksi.statusPesananTransaksi.index', compact('statusTransaksi'));
    }
}
