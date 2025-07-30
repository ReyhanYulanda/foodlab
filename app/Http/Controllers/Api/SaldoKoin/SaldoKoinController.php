<?php

namespace App\Http\Controllers\Api\SaldoKoin;

use App\Http\Controllers\Controller;
use App\Models\SaldoKoin;
use App\Models\TopUp;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaldoKoinController extends Controller
{
    public function cekSaldo()
    {
        $this->authorize('read saldo_koin');

        $userId = Auth::id();

        $pendingTopUps = TopUp::where('user_id', $userId)
            ->where('status_bayar', 1)
            ->where('isTf', 0)
            ->get();

        if ($pendingTopUps->count() > 0) {
            $saldo = SaldoKoin::firstOrCreate(['user_id' => $userId], ['jumlah' => 0]);

            $totalTopup = $pendingTopUps->sum('nominal_topup');
            $saldo->jumlah += $totalTopup;
            $saldo->save();

            // ✅ Catat transaksi
            TransaksiSaldoKoin::create([
                'user_id' => $userId,
                'jumlah' => $totalTopup,
                'tipe' => 'masuk',
                'deskripsi' => 'Top-up berhasil melalui Virtual Account'
            ]);

            // ✅ Kirim notifikasi (opsional)
            $user = User::find($userId);
            if ($user && $user->fcm_token) {
                $firebases = new Firebases();
                $firebases->withData([
                    'title' => 'Top-up Berhasil',
                    'body' => 'Saldo sebesar Rp ' . number_format($totalTopup, 0, ',', '.') . ' telah ditambahkan ke akun Anda.'
                ])->sendMessages($user->fcm_token);
            }

            // ✅ Update status isTf
            TopUp::where('user_id', $userId)
                ->where('status_bayar', 1)
                ->where('isTf', 0)
                ->update(['isTf' => 1]);
        } else {
            $saldo = SaldoKoin::firstOrCreate(['user_id' => $userId], ['jumlah' => 0]);
        }

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
