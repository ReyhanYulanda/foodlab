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
