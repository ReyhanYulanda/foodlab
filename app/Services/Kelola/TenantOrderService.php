<?php

namespace App\Services\Kelola;

use App\Models\Tenants;
use App\Models\Transaksi;
use App\Response\ResponseApi;
use App\Helper\ValidationHelper;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Services\Firebases;
use App\Services\Midtrans;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantOrderService
{
    public function getDataPesanan($userId, $status = null)
    {
        try {
            $tenant = Tenants::where("user_id", $userId)->first();
            $dataPesanan = Transaksi::with([
                'listTransaksiDetail.menus.tenants' => function ($query) use ($tenant) {
                    $query->where('id', $tenant->id ?? null);
                },
                'user'
            ])
                ->whereHas('listTransaksiDetail.menus.tenants', function ($query) use ($tenant) {
                    $query->where('id', $tenant->id ?? null);
                })
                ->whereNotIn('status', ['pending', 'expire', 'cancel'])
                ->get();

            if ($status) {
                $dataPesanan = $dataPesanan->where('status', $status);
            }

            return $dataPesanan;
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function updateStatusPesanan($request, $firebases, $id)
    {
        $transaksi = Transaksi::with('user')->find($id);

        if (!$transaksi) {
            return ResponseApi::error('pesanan tidak ditemukan', 404);
        }

        if ($transaksi->status === 'refund_selesai') {
            return ResponseApi::error('Pesanan telah selesai refund system karena melebihi 10 menit.', 403);
        }

        if ($transaksi->status === 'pesanan_ditolak') {
            return ResponseApi::error('Pesanan sudah ditolak sebelumnya.', 403);
        }

        if ($transaksi->status === 'selesai') {
            return ResponseApi::error('Pesanan sudah selesai.', 403);
        }

        $validation = ValidationHelper::validate($request->all(), [
            'status' => 'required|in:pesanan_ditolak,pesanan_diproses,siap_diantar,siap_diambil,diantar,selesai'
        ]);

        if ($transaksi->status === 'pesanan_diproses' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan sudah dalam proses sebelumnya.', 403);
        }

        if ($transaksi->status === 'pesanan_diproses' && $request->status === 'pesanan_ditolak') {
            return ResponseApi::error('Pesanan sedang diproses, tidak bisa ditolak.', 403);
        }

        if ($transaksi->status === 'siap_diantar' && $request->status === 'siap_diantar') {
            return ResponseApi::error('Pesanan sudah siap diantar sebelumnya.', 403);
        }

        if ($transaksi->status === 'siap_diambil' && $request->status === 'siap_diambil') {
            return ResponseApi::error('Pesanan sudah siap diambil sebelumnya.', 403);
        }

        if ($transaksi->status === 'diantar' && $request->status === 'diantar') {
            return ResponseApi::error('Pesanan sudah dalam proses pengantaran sebelumnya.', 403);
        }

        if ($transaksi->status === 'selesai' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan sudah selesai sebelumnya.', 403);
        }

        if ($transaksi->status === 'pesanan_ditolak' && $request->status === 'pesanan_ditolak') {
            return ResponseApi::error('Pesanan sudah ditolak sebelumnya.', 403);
        }

        if ($validation) {
            return $validation;
        }

        if ($transaksi->status === 'pesanan_masuk' && $request->status === 'pesanan_diproses' && $transaksi->isPriority == 1) {
            if ($transaksi->driver_id == null) {
                //kirim notif ke driver
                $masbroOfflineTokens = User::role('masbro')
                    // ->where('isOnline', 0)
                    ->with('fcmTokens')
                    ->get()
                    ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                if (!empty($masbroOfflineTokens)) {
                    $firebases
                        ->withNotification('Ada Pesanan Prioritas', 'Gasin yuk ada ongkir tambahannya loh')
                        ->withData([
                            'title' => 'Ada Pesanan Prioritas',
                            'body' => 'Gasin yuk ada ongkir tambahannya loh',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToDriver($masbroOfflineTokens);
                }
            } else {
                //kirim notif ke driver sesuai transaksi->driver_id
                $driver = User::with('fcmTokens')->find($transaksi->driver_id);
                $fcmDriverToken = $driver ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title' => 'Perubahan status pesanan prioritas',
                            'body' => 'Cek status pesanan prioritas',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToDriver($fcmDriverToken);
                }
            }
        }

        if (
            $transaksi->status === 'pesanan_diproses' &&
            $request->status === 'siap_diantar' &&
            $transaksi->isPriority == 1
        ) {
            // Cek apakah sudah ada driver
            if ($transaksi->driver_id !== null) {
                // Sudah ada driver → langsung skip ke "diantar"
                $request->merge(['status' => 'diantar']);
                // kirim notif ke driver
                $driver = User::with('fcmTokens')->find($transaksi->driver_id);
                $fcmDriverToken = $driver ? $driver->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];
                if (!empty($fcmDriverToken)) {
                    $firebases
                        ->withNotification('Perubahan status pesanan prioritas', 'Cek status pesanan prioritas')
                        ->withData([
                            'title' => 'Perubahan status pesanan prioritas',
                            'body' => 'Cek status pesanan prioritas',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])->sendToDriver($fcmDriverToken);
                }
                Log::info("Pesanan prioritas #{$transaksi->id} otomatis diubah menjadi 'diantar' karena sudah memiliki driver.");
            } else {
                // Belum ada driver → tetap flow normal
                Log::info("Pesanan prioritas #{$transaksi->id} masih menunggu driver, tetap di 'siap_diantar'.");
                // kirim notif ke driver

            }
        }

        if (
            $request->status === 'selesai' &&
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

        $transaksi->status = $request->status;
        $transaksi->save();

        try {
            if ($transaksi->metode_pembayaran != 'transfer') {
                $transaksi->listTransaksiDetail()->update(['status' => $transaksi->status]);
            }

            $this->sendNotifications($transaksi, $firebases);

            return ResponseApi::success(null, "Pesanan $transaksi->status");
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return ResponseApi::error($e->getMessage());
        }
    }

    private function sendNotifications($transaksi, $firebases)
    {
        $masbroTokens = User::role('masbro')
            ->where('isOnline', 1)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $masbroOfflineTokens = User::role('masbro')
            ->where('isOnline', 0)
            ->with('fcmTokens')
            ->get()
            ->flatMap(fn($user) => $user->fcmTokens->pluck('fcm_token'))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $user = User::find($transaksi->user_id);

        // Pastikan token user pembeli dalam bentuk array
        $fcmUser = User::with('fcmTokens')->find($transaksi->user_id);
        $fcmUserToken = $fcmUser ? $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() : [];

        // SEND TO USER (Pembeli)
        $sendToUser = function ($title, $body, $type) use ($firebases, $transaksi, $fcmUserToken) {
            if (!empty($fcmUserToken)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $type,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($fcmUserToken);
            }
        };

        // SEND TO TENANT
        $sendToTenant = function ($title, $body, $type) use ($firebases, $transaksi) {
            $pemilik = optional($transaksi->tenant)->pemilik;

            if ($pemilik) {
                $tokens = $pemilik->fcmTokens()->pluck('fcm_token')->filter()->unique()->toArray();

                if (!empty($tokens)) {
                    $firebases->withNotification($title, $body)
                        ->withData([
                            'title' => $title,
                            'body' => $body,
                            'type' => $type,
                            'transaksi_id' => $transaksi->id,
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        ])
                        ->sendToTenant($tokens);
                }
            }
        };

        // SEND TO DRIVER/MASBRO
        $sendToDrivers = function ($title, $body, $type) use ($firebases, $transaksi, $masbroTokens) {
            if (!empty($masbroTokens)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $type,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToDriver($masbroTokens);
            }
        };


        $sendToOfflineDrivers = function ($title, $body, $type) use ($firebases, $transaksi, $masbroOfflineTokens) {
            if (!empty($masbroOfflineTokens)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $type,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($masbroOfflineTokens);
            }
        };

        // === LOGIKA NOTIFIKASI BERDASARKAN STATUS ===
        if ($transaksi->status === 'pesanan_diproses') {
            $sendToUser(
                'Pesanan Sedang Diproses',
                "Pesanan {$transaksi->id} sedang dibuat oleh tenant. Mohon ditunggu, ya!",
                'pesanan_diproses'
            );
        }

        if ($transaksi->status === 'siap_diantar') {
            $sendToUser(
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->id} selesai dibuat. Kami sedang mencari driver untuk mengantar pesananmu",
                'siap_diantar'
            );

            $sendToDrivers(
                'Ada Pesanan Siap Diantar',
                "Pesanan {$transaksi->id} sudah siap. Yuk, ambil dan antar sekarang!",
                'siap_diantar_driver'
            );

            $sendToOfflineDrivers(
                'Ada Pesanan Siap Diantar Loh',
                "Pesanan ke {$transaksi->id}. Yuk, nyalain status drivermu!",
                'siap_diantar_driver'
            );
        }

        if ($transaksi->status === 'siap_diambil') {
            $sendToUser(
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->id} selesai dibuat. Yuk ambil pesanananmu sekarang",
                'siap_diambil'
            );
        }

        if ($transaksi->status === 'diantar') {
            $sendToUser(
                'Pesanan Sedang Diantar',
                "Pesanan {$transaksi->id} sedang diantar oleh driver. Silakan tunggu sebentar.",
                'diantar'
            );

            // $sendToDrivers(
            //     'Ada Pesanan Baru',
            //     "Pesanan {$transaksi->id} sedang diantar. Yuk, bantu antar!",
            //     'diantar_driver'
            // );
        }

        if ($transaksi->status === 'selesai') {
            $sendToUser(
                'Pesanan Selesai',
                "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽",
                'selesai'
            );
        }

        if ($transaksi->status === 'pesanan_masuk') {
            $sendToTenant(
                'Pesanan Masuk',
                'Ada pesanan baru masuk di tenant kamu. Yuk, segera proses!',
                'pesanan_masuk'
            );
        }
    }
}
