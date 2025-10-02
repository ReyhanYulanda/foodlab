<?php

namespace App\Http\Controllers\Web\Transaksi;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Transaksi;
use App\Models\Pengaturan;
use App\Models\User;
use Illuminate\Http\Request;

class TransaksiDriverController extends Controller
{
    public function TransaksiDriver(Request $request)
    {
        // Ambil persentase biaya ongkir (default 10%)
        $pengaturanPotongan = Pengaturan::where('nama', 'biaya_ongkos_kirim')->first();
        $persentasePotongan = $pengaturanPotongan ? (float)$pengaturanPotongan->nilai : 10;

        // Query transaksi
        $query = Transaksi::select(
            'driver_id',
            DB::raw('SUM(ongkos_kirim) as total_ongkir')
        )
            ->whereNotNull('driver_id')
            ->where('status', 'selesai');

        // Filter berdasarkan tanggal
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $data = $query->groupBy('driver_id')
            ->with(['driver.koin']) 
            ->get()
            ->map(function ($item) use ($persentasePotongan) {
                $item->pendapatan_pens = $item->total_ongkir * $persentasePotongan / 100;
                $item->pendapatan_driver = $item->total_ongkir - $item->pendapatan_pens;
                $item->saldo_driver = $item->driver->saldo ?? 0; 
                return $item;
            });

        return view('pages.transaksi.driver.index', compact('data'));
    }

    public function detailTransaksiDriver(Request $request, $driver_id)
    {
        $driver = User::find($driver_id);

        if (!$driver) {
            return redirect()->back()->with('error', 'Driver tidak ditemukan.');
        }

        $query = Transaksi::where('driver_id', $driver_id)
            ->where('status', 'selesai');

        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $transaksi = $query->get();

        return view('pages.transaksi.rincianTransaksiDriver.index', compact('driver', 'transaksi'));
    }

    public function detailPencairanTransaksiDriver($driver_id) {}
}
