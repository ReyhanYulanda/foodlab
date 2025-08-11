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

        DB::transaction(function () use ($userId) {
            $pendingTopUps = TopUp::where('user_id', $userId)
                ->where('isTf', 0)
                ->whereIn('status_bayar', ['1', 'settlement'])
                ->lockForUpdate()
                ->get();

            if ($pendingTopUps->isNotEmpty()) {
                Log::info('Saldo sebelum top-up:', ['user_id' => $userId]);

                $saldo = SaldoKoin::firstOrCreate(
                    ['user_id' => $userId],
                    ['jumlah' => 0]
                );

                $user = User::with('fcmTokens')->find($userId);
                $fcmUserToken = $user ? $user->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                foreach ($pendingTopUps as $topup) {
                    $saldo->jumlah += $topup->nominal;
                    $saldo->save();

                    // Tentukan jenis pembayaran
                    if ($topup->status_bayar === '1') {
                        $deskripsi = 'Top-up berhasil melalui Virtual Account';
                        $notifTitle = 'Top-up Berhasil';
                        $notifBody = 'Saldo sebesar Rp ' . number_format($topup->nominal, 0, ',', '.') . ' telah ditambahkan melalui Virtual Account.';
                    } elseif ($topup->status_bayar === 'settlement') {
                        $deskripsi = 'Top-up berhasil melalui QRIS';
                        $notifTitle = 'Top-up Berhasil';
                        $notifBody = 'Saldo sebesar Rp ' . number_format($topup->nominal, 0, ',', '.') . ' telah ditambahkan melalui QRIS.';
                    } else {
                        continue; // Skip jika status tidak cocok
                    }

                    // Catat transaksi
                    TransaksiSaldoKoin::create([
                        'user_id' => $userId,
                        'jumlah' => $topup->nominal,
                        'tipe' => 'masuk',
                        'deskripsi' => $deskripsi
                    ]);

                    // Kirim notifikasi
                    if (!empty($fcmUserToken)) {
                        $firebases = new Firebases();
                        $firebases
                            ->withNotification($notifTitle, $notifBody)
                            ->withData([
                                'title' => $notifTitle,
                                'body' => $notifBody,
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ])->sendToFallback($fcmUserToken);
                    }

                    // Tandai topup sudah ditransfer
                    $topup->update(['isTf' => 1]);
                }

                Log::info('Saldo setelah top-up:', [
                    'user_id' => $userId,
                    'saldo' => $saldo->jumlah
                ]);
            }
        });

        $saldo = SaldoKoin::firstOrCreate(['user_id' => $userId], ['jumlah' => 0]);

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
