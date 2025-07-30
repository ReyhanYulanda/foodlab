<?php

namespace App\Http\Controllers\Api\SaldoKoin;

use App\Http\Controllers\Controller;
use App\Models\SaldoKoin;
use App\Models\TopUp;
use App\Models\TransaksiSaldoKoin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaldoKoinController extends Controller
{
    public function cekSaldo()
    {
        $this->authorize('read saldo_koin');

        $userId = Auth::id();

        DB::transaction(function () use ($userId, &$saldo) {
            // Ambil semua topup yang sudah dibayar tapi belum ditransfer
            $pendingTopUps = TopUp::where('user_id', $userId)
                ->where('status_bayar', 1)
                ->where('isTf', 0)
                ->lockForUpdate() // ⛔ Kunci baris agar tidak diproses paralel
                ->get();

            $saldo = SaldoKoin::firstOrCreate(
                ['user_id' => $userId],
                ['jumlah' => 0]
            );

            if ($pendingTopUps->count() > 0) {
                $totalTopup = $pendingTopUps->sum('nominal_topup');

                $saldo->jumlah += $totalTopup;
                $saldo->save();

                // Tandai topup sudah ditransfer
                TopUp::whereIn('id', $pendingTopUps->pluck('id'))
                    ->update(['isTf' => 1]);
            }
        });

        return response()->json([
            'success' => true,
            'saldo_koin' => $saldo->jumlah
        ]);
    }


    public function riwayatTransaksi()
    {
        $this->authorize('read saldo_koin');
        $transaksi = TransaksiSaldoKoin::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'transaksi' => $transaksi
        ]);
    }
}
