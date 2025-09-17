<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cashback;
use App\Models\Ruangan;
use Illuminate\Http\Request;
use App\Models\Transaksi;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index()
    {
        $totalTransaksi = Transaksi::count();
        $totalNominal = Transaksi::sum('total_bayar');
        $transaksiBulanIni = Transaksi::whereMonth('created_at', now()->month)->count();

        $bulanLabels = collect(range(1, 12))->map(function ($m) {
            return date('M', mktime(0, 0, 0, $m, 1));
        });
        $transaksiPerBulan = collect(range(1, 12))->map(function ($m) {
            return Transaksi::whereMonth('created_at', $m)->count();
        });

        $tanggalLabels = range(1, now()->daysInMonth);
        $transaksiPerTanggal = collect($tanggalLabels)->map(function ($d) {
            return Transaksi::whereDay('created_at', $d)
                ->whereMonth('created_at', now()->month)
                ->count();
        });

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
