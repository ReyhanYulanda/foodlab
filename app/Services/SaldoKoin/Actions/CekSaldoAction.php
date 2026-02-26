<?php

namespace App\Services\SaldoKoin\Actions;

use App\Repositories\SaldoKoinRepository;
use App\Repositories\TopUpRepository;
use App\Repositories\TransaksiSaldoKoinRepository;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CekSaldoAction
{
    protected SaldoKoinRepository $saldoRepo;
    protected TopUpRepository $topUpRepo;
    protected TransaksiSaldoKoinRepository $transaksiRepo;

    public function __construct(
        SaldoKoinRepository $saldoRepo,
        TopUpRepository $topUpRepo,
        TransaksiSaldoKoinRepository $transaksiRepo
    ) {
        $this->saldoRepo = $saldoRepo;
        $this->topUpRepo = $topUpRepo;
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(int $userId)
    {
        DB::transaction(function () use ($userId) {
            $pendingTopUps = $this->topUpRepo->getPendingByUser($userId);

            if ($pendingTopUps->isNotEmpty()) {
                Log::info('Saldo sebelum top-up:', ['user_id' => $userId]);

                $saldo = $this->saldoRepo->firstOrCreate($userId);

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
                        continue;
                    }

                    // Catat transaksi
                    $this->transaksiRepo->create([
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

        $saldo = $this->saldoRepo->firstOrCreate($userId);

        return [
            'saldo_koin' => $saldo->jumlah
        ];
    }
}
