<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use Illuminate\Http\Request;

class TransaksiDriverController extends Controller
{
    public function TransaksiDriver()
    {
        // Ambil persentase biaya ongkir dari tabel pengaturan (default 10%)
        $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
        $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 10;

        // Ambil transaksi yang selesai dan sudah ada driver_id
        $data = Transaksi::select(
            'driver_id',
            DB::raw('SUM(ongkos_kirim) as total_ongkir')
        )
            ->whereNotNull('driver_id')
            ->where('status', 'selesai')
            ->groupBy('driver_id')
            ->with('driver')
            ->get()
            ->map(function ($item) use ($persentasePotongan) {
                // Hitung pendapatan Pens & Driver
                $item->pendapatan_pens = $item->total_ongkir * $persentasePotongan / 100;
                $item->pendapatan_driver = $item->total_ongkir - $item->pendapatan_pens;
                return $item;
            });

        return view('pages.transaksi.driver.index', compact('data'));
    }

    public function detailTransaksiDriver($driver_id)
    {
        $driver = Transaksi::find($driver_id);

        if (!$driver) {
            return redirect()->back()->with('error', 'Driver tidak ditemukan.');
        }

        $transaksi = Transaksi::where('driver_id', $driver_id)
            ->where('status', 'selesai')
            ->get();

        return view('pages.transaksi.rincianTransaksiDriver.index', compact('driver', 'transaksi'));
    }
}
