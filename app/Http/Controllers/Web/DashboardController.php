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
        $totalTransaksi = Transaksi::count();
        $totalNominal = Transaksi::sum('total'); // ganti sesuai kolom yg benar
        $transaksiBulanIni = Transaksi::whereMonth('created_at', now()->month)->count();

        // Label bulan (Jan - Dec)
        $bulanLabels = collect(range(1, 12))->map(function ($m) {
            return date('M', mktime(0, 0, 0, $m, 1));
        });

        // Data transaksi per bulan
        $transaksiPerBulan = collect(range(1, 12))->map(function ($m) {
            return Transaksi::whereMonth('created_at', $m)->count();
        });

        // Statistik status
        $pesananSelesai = Transaksi::where('status', 'selesai')->count();
        $pesananRefund  = Transaksi::where('status', 'refund_selesai')->count();

        return view('dashboard', compact(
            'totalTransaksi',
            'totalNominal',
            'transaksiBulanIni',
            'bulanLabels',
            'transaksiPerBulan',
            'pesananSelesai',
            'pesananRefund'
        ));
    }
}
