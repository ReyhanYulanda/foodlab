<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index()
    {
        // Total transaksi (jumlah order)
        $totalTransaksi = Transaksi::count();

        // Total nominal: SUM dari detail (jumlah * harga)
        $totalNominal = TransaksiDetail::select(DB::raw('SUM(jumlah * harga) as total'))->value('total');

        // Transaksi bulan ini (jumlah order)
        $transaksiBulanIni = Transaksi::whereMonth('created_at', now()->month)->count();

        // Data per bulan (jumlah order)
        $bulanLabels = collect(range(1, 12))->map(fn($m) => date('M', mktime(0, 0, 0, $m, 1)));
        $transaksiPerBulan = collect(range(1, 12))->map(
            fn($m) =>
            Transaksi::whereMonth('created_at', $m)->count()
        );

        // Data per tanggal (bulan ini, jumlah order)
        $tanggalLabels = range(1, now()->daysInMonth);
        $transaksiPerTanggal = collect($tanggalLabels)->map(
            fn($d) =>
            Transaksi::whereDay('created_at', $d)
                ->whereMonth('created_at', now()->month)
                ->count()
        );

        return view('dashboard', compact(
            'totalTransaksi',
            'totalNominal',
            'transaksiBulanIni',
            'bulanLabels',
            'transaksiPerBulan',
            'tanggalLabels',
            'transaksiPerTanggal'
        ));
    }
}
