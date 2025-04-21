<?php

namespace App\Http\Controllers\Api\SaldoKoin;

use App\Http\Controllers\Controller;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaldoKoinController extends Controller
{
    public function cekSaldo()
    {
        $saldo = SaldoKoin::where('user_id', Auth::id())->first();

        return response()->json([
            'success' => true,
            'saldo_koin' => $saldo ? $saldo->jumlah : 0
        ]);
    }

    // public function tambahSaldo(Request $request)
    // {
    //     $request->validate([
    //         'jumlah' => 'required|integer|min:0',
    //         'deskripsi' => 'nullable|string|max:255'
    //     ]);

    //     $saldo = SaldoKoin::firstOrCreate(['user_id' => Auth::id()]);
    //     $saldo->jumlah += $request->jumlah;
    //     $saldo->save();

    //     TransaksiSaldoKoin::create([
    //         'user_id' => Auth::id(),
    //         'jumlah' => $request->jumlah,
    //         'tipe' => 'masuk',
    //         'deskripsi' => $request->deskripsi ?? 'Penambahan Saldo Koin'
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Saldo Koin berhasil ditambahkan',
    //         'saldo_koin' => $saldo->jumlah
    //     ]);
    // }

    // public function kurangiSaldo(Request $request)
    // {
    //     $request->validate([
    //         'jumlah' => 'required|integer|min:0',
    //         'deskripsi' => 'nullable|string|max:255'
    //     ]);

    //     $saldo = SaldoKoin::where('user_id', Auth::id())->first();

    //     if (!$saldo || $saldo->jumlah < $request->jumlah) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Saldo Koin tidak cukup'
    //         ], 400);
    //     }

    //     $saldo->jumlah -= $request->jumlah;
    //     $saldo->save();

    //     // Simpan transaksi
    //     TransaksiSaldoKoin::create([
    //         'user_id' => Auth::id(),
    //         'jumlah' => $request->jumlah,
    //         'tipe' => 'keluar',
    //         'deskripsi' => $request->deskripsi ?? 'Pengurangan Saldo Koin'
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Saldo Koin berhasil dikurangi',
    //         'saldo_koin' => $saldo->jumlah
    //     ]);
    // }

    public function riwayatTransaksi()
    {
        $transaksi = TransaksiSaldoKoin::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'transaksi' => $transaksi
        ]);
    }
}
