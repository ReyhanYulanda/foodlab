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
use Illuminate\Support\Facades\Log;

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
            Log::info('Saldo sebelum top-up:', ['user_id' => $userId]);

            $saldo = SaldoKoin::firstOrCreate(['user_id' => $userId], ['jumlah' => 0]);

            $totalTopup = $pendingTopUps->sum('nominal');
            $saldo->jumlah += $totalTopup;
            $saldo->save();
            Log::info('Total yang ditambah ke saldo:', [$totalTopup]);

            TransaksiSaldoKoin::create([
                'user_id' => $userId,
                'jumlah' => $totalTopup,
                'tipe' => 'masuk',
                'deskripsi' => 'Top-up berhasil melalui Virtual Account'
            ]);
            Log::info('Saldo setelah top-up:', ['user_id' => $userId, 'saldo' => $saldo->jumlah]);

            // ✅ Kirim notifikasi (menggunakan fcmTokens relasi)
            $user = User::with('fcmTokens')->find($userId);
            $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

            if (!empty($fcmUserToken)) {
                $firebases = new Firebases();
                $firebases
                    ->withNotification(
                        'Top-up Berhasil',
                        'Saldo sebesar Rp ' . number_format($totalTopup, 0, ',', '.') . ' telah ditambahkan ke akun Anda.'
                    )
                    ->withData([
                        'title' => 'Top-up Berhasil',
                        'body' => 'Saldo sebesar Rp ' . number_format($totalTopup, 0, ',', '.') . ' telah ditambahkan ke akun Anda.',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ])->sendToFallback($fcmUserToken);
            }

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
                return response()->json(['message' => 'Saldo pengirim tidak mencukupi'], 422);
            }

            // ambil user detail
            $senderUser   = User::find($request->sender_id);
            $receiverUser = User::find($request->receiver_id);

            // update saldo
            $senderSaldo->decrement('jumlah', $request->jumlah);
            $receiverSaldo ? $receiverSaldo->increment('jumlah', $request->jumlah)
                : SaldoKoin::create([
                    'user_id' => $request->receiver_id,
                    'jumlah'  => $request->jumlah
                ]);

            // catat transaksi pengirim (keluar)
            TransaksiSaldoKoin::create([
                'user_id'   => $request->sender_id,
                'jumlah'    => $request->jumlah,
                'tipe'      => 'keluar',
                'deskripsi' => 'Transfer koin ke ' . $receiverUser->name
            ]);

            // catat transaksi penerima (masuk)
            TransaksiSaldoKoin::create([
                'user_id'   => $request->receiver_id,
                'jumlah'    => $request->jumlah,
                'tipe'      => 'masuk',
                'deskripsi' => 'Menerima koin dari ' . $senderUser->name
            ]);

            return response()->json([
                'message' => 'Transfer sebesar Rp ' . number_format($request->jumlah, 0, ',', '.') . ' berhasil',
                'data'    => [
                    'sender'   => $senderSaldo->fresh(),
                    'receiver' => $receiverSaldo ? $receiverSaldo->fresh() : SaldoKoin::where('user_id', $request->receiver_id)->first()
                ]
            ]);
        });
    }
}
