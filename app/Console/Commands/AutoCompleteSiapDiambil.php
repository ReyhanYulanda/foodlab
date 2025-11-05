<?php

namespace App\Console\Commands;

use App\Models\SaldoKoin;
use App\Models\Transaksi;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class AutoCompleteSiapDiambil extends Command
{
    protected $signature = 'transaksi:auto-complete-siap-diambil';
    protected $description = 'Otomatis mengubah status siap_diambil menjadi selesai setiap jam 2 pagi';

    public function handle(Firebases $firebases)
    {
        $count = 0;

        $transaksiList = Transaksi::where('status', 'siap_diambil')->get();

        foreach ($transaksiList as $transaksi) {
            $transaksi->status = 'selesai';
            $transaksi->updated_at = Carbon::now('Asia/Jakarta');
            $transaksi->save();

            if ($transaksi->metode_pembayaran != 'transfer') {
                $transaksi->listTransaksiDetail()->update(['status' => 'selesai']);
            }
            if (
                $transaksi->status === 'selesai' &&
                $transaksi->cashback_amount > 0 &&
                $transaksi->status !== 'selesai'
            ) {
                $user = $transaksi->user;

                // Ambil saldo koin user, kalau belum ada buat baru
                $saldo = SaldoKoin::firstOrCreate(
                    ['user_id' => $user->id],
                    ['jumlah' => 0]
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

                // Logging
                Log::info("Cashback: {$transaksi->cashback_amount} telah diterima oleh {$user->name}");

                // Kirim notifikasi FCM
                $fcmUser = User::with('fcmTokens')->find($user->id);
                $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

                if (!empty($fcmUserToken)) {
                    $title = 'Cashback berhasil didapatkan';
                    $body  = "Cashback sebanyak {$transaksi->cashback_amount} berhasil masuk ke akunmu.";

                    $firebases->withNotification($title, $body)
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
            $count++;
        }

        $this->info("$count transaksi berhasil diupdate menjadi selesai.");
        Log::info("$count transaksi berhasil diupdate menjadi selesai pada " . Carbon::now('Asia/Jakarta')->toDateTimeString());
        return Command::SUCCESS;
    }
}
