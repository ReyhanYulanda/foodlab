<?php

namespace App\Http\Controllers\Web\SaldoKoin;

use App\Http\Controllers\Controller;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferCoinController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('pages.transfer-koin.index', compact('users'));
    }

    public function transferCoin(Request $request)
    {
        $this->authorize('read transfer_coin');

        $request->validate([
            'sender_id'   => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id|different:sender_id',
            'jumlah'      => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($request) {
            $senderSaldo   = SaldoKoin::where('user_id', $request->sender_id)->lockForUpdate()->first();
            $receiverSaldo = SaldoKoin::where('user_id', $request->receiver_id)->lockForUpdate()->first();

            if (!$senderSaldo || $senderSaldo->jumlah < $request->jumlah) {
                return back()->withErrors(['message' => 'Saldo pengirim tidak mencukupi']);
            }

            $senderUser   = User::find($request->sender_id);
            $receiverUser = User::find($request->receiver_id);

            $senderSaldo->decrement('jumlah', $request->jumlah);
            $receiverSaldo ? $receiverSaldo->increment('jumlah', $request->jumlah)
                : SaldoKoin::create([
                    'user_id' => $request->receiver_id,
                    'jumlah'  => $request->jumlah
                ]);

            TransaksiSaldoKoin::create([
                'user_id'   => $request->sender_id,
                'jumlah'    => $request->jumlah * (-1),
                'tipe'      => 'keluar',
                'deskripsi' => 'Transfer koin ke ' . $receiverUser->name
            ]);

            TransaksiSaldoKoin::create([
                'user_id'   => $request->receiver_id,
                'jumlah'    => $request->jumlah,
                'tipe'      => 'masuk',
                'deskripsi' => 'Menerima koin dari ' . $senderUser->name
            ]);

            return redirect()->route('transfer.coin.index')->with('success', 'Transfer berhasil');
        });
    }
}
