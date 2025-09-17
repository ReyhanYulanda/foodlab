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
        $totalNominal = Transaksi::sum('total_bayar_user'); // pakai kolom sesuai model
        $transaksiBulanIni = Transaksi::whereMonth('created_at', now()->month)->count();

        // Labels bulan (Jan - Dec)
        $labels = collect(range(1, 12))->map(function ($m) {
            return date('M', mktime(0, 0, 0, $m, 1));
        });

        // Data transaksi selesai per bulan
        $selesai = collect(range(1, 12))->map(function ($m) {
            return Transaksi::whereMonth('created_at', $m)
                ->where('status_bayar', 'selesai')
                ->count();
        });

        // Data refund per bulan
        $refund = collect(range(1, 12))->map(function ($m) {
            return Transaksi::whereMonth('created_at', $m)
                ->where('status_bayar', 'refund')
                ->count();
        });

        return view('dashboard', compact(
            'totalTransaksi',
            'totalNominal',
            'transaksiBulanIni',
            'labels',
            'selesai',
            'refund'
        ));
    }
}
