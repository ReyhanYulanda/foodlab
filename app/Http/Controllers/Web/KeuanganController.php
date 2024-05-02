<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KeuanganController extends Controller
{
    public function index(){
        $this->authorize('read pesanan');
        $user = Auth::user();

        $tenant = Tenants::where("user_id", $user->id)->first();
        $dataPesanan = Transaksi::with([
            'listTransaksiDetail' => function ($query) use ($tenant) {
                $query->whereHas('menusKelola', function($menusKelola) use($tenant){
                    $menusKelola->where('tenant_id', $tenant->id);
                });
            }
        , 'user'])->whereHas('listTransaksiDetail.menusKelola.tenants', function ($query) use ($tenant) {
            $query->where('id', $tenant->id);
        })
        ->whereIn('status', ['selesai'])
        ->get();

        $summary = DB::table('transaksi_detail')
                        ->join('transaksi', 'transaksi.id', 'transaksi_detail.transaksi_id')
                        ->join('menus_kelola', 'transaksi_detail.menus_kelola_id', 'menus_kelola.id')
                        ->join('tenants', 'tenants.id', 'menus_kelola.tenant_id')
                        ->select('transaksi_detail.*', 'transaksi.status as transaksi_status', 'transaksi.metode_pembayaran')
                        ->addSelect(DB::raw('transaksi_detail.harga * jumlah as total_harga_menu'))
                        ->where('transaksi.status', 'selesai')
                        ->where('tenants.id', $tenant->id);

        $summary = DB::table(DB::raw("({$summary->toSql()}) as query"))
            ->mergeBindings($summary)
            ->select('query.*');

        $summaryPending = (clone $summary)->whereIn('status', ['pending', 'pesanan_masuk'])->where('metode_pembayaran', 'transfer')->sum('total_harga_menu');
        $summaryMingguIni = (clone $summary)->whereBetween('created_at', [Carbon::now()->startOfWeek()->toDateTimeString(), Carbon::now()->endOfWeek()->toDateTimeString()])->sum('total_harga_menu');
        $summaryBulanIni = (clone $summary)->whereBetween('created_at', [Carbon::now()->startOfMonth()->toDateTimeString(), Carbon::now()->endOfMonth()->toDateTimeString()])->sum('total_harga_menu');
        $summarySemua = (clone $summary)->sum('total_harga_menu');
        // $summaryMingguIni = $summary->whereIn('status', ['selesai'])->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->sum('total_harga_menu');
        // $summaryBulanIni = $summary->whereIn('status', ['selesai'])->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->sum('total_harga_menu');
        // $summarySemua = $summary->whereIn('status', ['selesai'])->sum('total_harga_menu');

        $summary = (Object)[
            "jumlah_pending" => $summaryPending,
            "jumlah_minggu_ini" => $summaryMingguIni,
            "jumlah_bulan_ini" => $summaryBulanIni,
            "jumlah_semua" => $summarySemua,
        ];

        return view('pages.keuangan.index', compact('dataPesanan', 'summary'));
    }
}
