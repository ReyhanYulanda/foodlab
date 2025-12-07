<?php

namespace App\Actions;

use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class ProcessCashbackAction
{
    /**
     * Memproses cashback ketika status berubah jadi "selesai".
     */
    public function execute(Transaksi $transaksi, string $newStatus, Firebases $firebases): void
    {
        if (
            $newStatus === 'selesai' &&
            $transaksi->cashback_amount > 0 &&
            $transaksi->status !== 'selesai'
        ) {
            $user = $transaksi->user ?? User::find($transaksi->user_id);

            if (!$user) {
                return;
            }

            // Ambil saldo koin user, kalau belum ada buat baru
            $saldo = SaldoKoin::firstOrCreate(
                ['user_id' => $user->id],
                ['jumlah'  => 0]
            );

            // Tambahkan cashback ke saldo
            $saldo->jumlah += $transaksi->cashback_amount;
            $saldo->save();

            // Catat di TransaksiSaldoKoin
            TransaksiSaldoKoin::create([
                'user_id'   => $user->id,
                'jumlah'    => $transaksi->cashback_amount,
                'tipe'      => 'masuk',
                'deskripsi' => "Cashback pesanan {$transaksi->kode_pemesanan} telah masuk",
            ]);

            Log::info("Cashback: {$transaksi->cashback_amount} telah diterima oleh {$user->name}");

            // Kirim notifikasi FCM ke user
            $fcmUser = User::with('fcmTokens')->find($user->id);
            $fcmUserToken = $fcmUser
                ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                : [];

            if (!empty($fcmUserToken)) {
                $title = 'Cashback berhasil didapatkan';
                $body  = "Cashback sebanyak {$transaksi->cashback_amount} berhasil masuk ke akunmu.";

                $firebases
                    ->withNotification($title, $body)
                    ->withData([
                        'title'        => $title,
                        'body'         => $body,
                        'type'         => 'cashback',
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($fcmUserToken);
            }
        }
    }
}
