<?php

namespace App\Services\Kelola;

use App\Models\Tenants;
use App\Models\Transaksi;
use App\Response\ResponseApi;
use App\Helper\ValidationHelper;
use App\Models\Cashier;
use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Services\Firebases;
use App\Services\Midtrans;
use App\Models\User;
use Carbon\Carbon;
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

        // if ($transaksi->status === 'pesanan_diproses' && $request->status === 'pesanan_ditolak') {
        //     return ResponseApi::error('Pesanan sedang diproses, tidak bisa ditolak.', 403);
        // }


        if ($transaksi->status === 'siap_diantar' && $request->status === 'siap_diantar') {
            return ResponseApi::error('Pesanan sudah siap diantar sebelumnya.', 403);
        }


        if ($transaksi->status === 'siap_diambil' && $request->status === 'siap_diambil') {
            return ResponseApi::error('Pesanan sudah siap diambil sebelumnya.', 403);
        }

        if ($transaksi->status === 'diantar' && $request->status === 'diantar') {
            return ResponseApi::error('Pesanan sudah dalam proses pengantaran sebelumnya.', 403);
        }

        if ($transaksi->status === 'selesai' && $request->status === 'siap_diambil') {
            return ResponseApi::error('Pesanan sudah siap diambil sebelumnya.', 403);
        }

        if ($transaksi->status === 'diantar' && $request->status === 'siap_diantar') {
            return ResponseApi::error('Pesanan sudah dalam proses pengantaran sebelumnya.', 403);
        }

        if ($transaksi->status === 'selesai' && $request->status === 'siap_diantar') {
            return ResponseApi::error('Pesanan sudah selesai sebelumnya.', 403);
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

        if (
            $request->status === 'selesai' && $transaksi->status !== 'selesai' && $transaksi->isAntar == 0
        ) {
            if ($transaksi->multitenant_id) {

                $related = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();

                if ($related->count() == 2) {

                    $t1 = $related[0];
                    $t2 = $related[1];

                    // CASE 1: Keduanya siap_diambil → selesai keduanya
                    if ($t1->status === 'siap_diambil' && $t2->status === 'siap_diambil') {

                        foreach ($related as $t) {
                            $this->completeTransaction($t);
                            $this->processCashback($t, $firebases);
                        }

                        Log::info("Multitenant {$transaksi->multitenant_id}: kedua transaksi selesai (manual oleh admin).");

                        // Karena kedua transaksi sudah diupdate, kita bisa return response
                        return response()->json([
                            'status' => 'success',
                            'message' => 'Kedua transaksi multitenant berhasil diselesaikan',
                            'data' => $transaksi->fresh()
                        ]);
                    }

                    // CASE 2: Salah satu refund_selesai
                    elseif (
                        ($t1->status === 'refund_selesai' && $t2->status === 'siap_diambil') ||
                        ($t2->status === 'refund_selesai' && $t1->status === 'siap_diambil')
                    ) {

                        $siap = $t1->status === 'siap_diambil' ? $t1 : $t2;
                        $refund = $t1->status === 'refund_selesai' ? $t1 : $t2;

                        // Kembalikan dana refund + cashback
                        $this->refundBalance($refund, $firebases);

                        // Selesaikan yang siap_diambil
                        $this->completeTransaction($siap);
                        $this->processCashback($siap, $firebases);

                        Log::info("Multitenant {$transaksi->multitenant_id}: ada refund → pengembalian & selesai 1 transaksi (manual oleh admin).");

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Transaksi berhasil diselesaikan, dana refund telah dikembalikan',
                            'data' => $siap->fresh()
                        ]);
                    }

                    // CASE 3: satu siap_diambil + satunya pesanan_diproses → hanya selesaikan yang siap_diambil
                    elseif (
                        ($t1->status === 'siap_diambil' && $t2->status === 'pesanan_diproses') ||
                        ($t2->status === 'siap_diambil' && $t1->status === 'pesanan_diproses')
                    ) {

                        $siap = $t1->status === 'siap_diambil' ? $t1 : $t2;

                        $this->completeTransaction($siap);
                        $this->processCashback($siap, $firebases);

                        Log::info("Multitenant {$transaksi->multitenant_id}: hanya 1 selesai (pasangan masih diproses, manual oleh admin)");

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Transaksi berhasil diselesaikan (pasangan transaksi masih diproses)',
                            'data' => $siap->fresh()
                        ]);
                    }

                    // CASE 4: Transaksi yang di-click sudah siap_diambil, pasangannya sudah selesai
                    elseif (
                        ($transaksi->id === $t1->id && $t1->status === 'siap_diambil' && $t2->status === 'selesai') ||
                        ($transaksi->id === $t2->id && $t2->status === 'siap_diambil' && $t1->status === 'selesai')
                    ) {

                        // Selesaikan transaksi yang di-click saja
                        $this->completeTransaction($transaksi);
                        $this->processCashback($transaksi, $firebases);

                        Log::info("Multitenant {$transaksi->multitenant_id}: melengkapi transaksi yang belum selesai (manual oleh admin)");

                        return response()->json([
                            'status' => 'success',
                            'message' => 'Transaksi berhasil diselesaikan',
                            'data' => $transaksi->fresh()
                        ]);
                    }

                    // CASE 5: Kondisi lain tidak memungkinkan (tidak ada yang siap_diambil)
                    else {
                        return response()->json([
                            'status' => 'failed',
                            'message' => 'Tidak dapat menyelesaikan transaksi. Pastikan status transaksi "siap_diambil"',
                        ], 400);
                    }
                }
            }
        }

        if (
            $transaksi->multitenant_id &&
            $transaksi->status === 'pesanan_diproses' &&
            $request->status === 'siap_diantar'
        ) {
            if ($transaksi->driver_id !== null) {
                $hasDelivered = Transaksi::where('multitenant_id', $transaksi->multitenant_id)
                    ->where('id', '!=', $transaksi->id)
                    ->where('status', 'diantar')
                    ->whereNotNull('driver_id')
                    ->exists();

                if ($hasDelivered) {
                    $request->merge(['status' => 'diantar']);
                    Log::info("Multitenant {$transaksi->multitenant_id} otomatis skip ke 'diantar' karena sudah ada pesanan lain yang diantar.");
                }
            }
        }

        $transaksi->status = $request->status;
        $transaksi->save();

        try {
            if ($transaksi->metode_pembayaran != 'transfer') {
                $transaksi->listTransaksiDetail()->update(['status' => $transaksi->status]);
            }

            $this->sendNotifications($transaksi, $firebases, $request);

            return ResponseApi::success(null, "Pesanan $transaksi->status");
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return ResponseApi::error($e->getMessage());
        }
    }

    public function updateStatusPesananCashier($request, $firebases, $id)
    {
        $validation = ValidationHelper::validate($request->all(), [
            'status' => 'required|in:pesanan_diproses,selesai',
        ]);

        if ($validation) {
            return $validation;
        }

        $cashier = Cashier::with('tenant.pemilik')->find($id);

        if (!$cashier) {
            return ResponseApi::error('Transaksi kasir tidak ditemukan', 404);
        }

        // Cek apakah user login adalah pemilik tenant
        $user = $request->user();
        $tenant = $cashier->tenant;

        if (!$tenant || $tenant->user_id !== $user->id) {
            return ResponseApi::forbidden('Kamu bukan pemilik tenant ini');
        }

        // Validasi status agar tidak double update
        if ($cashier->status === 'selesai' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir sudah selesai sebelumnya.', 400);
        }

        if ($cashier->status === 'selesai' && $request->status === 'pesnan_diproses') {
            return ResponseApi::error('Pesanan kasir sudah selesai sebelumnya.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'pending') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'gagal_bayar') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'pending') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'gagal_bayar') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        // Update status
        $cashier->status = $request->status;
        $cashier->save();

        // (Opsional) kirim notifikasi ke tenant atau user lain
        // $firebases->withNotification("Status kasir diperbarui", "Pesanan kasir #{$cashier->order_tenant} kini {$cashier->status}")
        //     ->withData([
        //         'title' => 'Status kasir diperbarui',
        //         'body' => "Pesanan kasir #{$cashier->order_tenant} kini {$cashier->status}",
        //     ])->sendToFallback([...]);

        return ResponseApi::success(null, "Status pesanan kasir berhasil diperbarui menjadi {$cashier->status}");
    }

    // ==============================
    // 🔧 HELPER: SET TRANSAKSI SELESAI
    // ==============================
    private function completeTransaction($transaksi)
    {
        $transaksi->status = 'selesai';
        $transaksi->updated_at = Carbon::now('Asia/Jakarta');
        $transaksi->save();

        if ($transaksi->metode_pembayaran != 'transfer') {
            $transaksi->listTransaksiDetail()->update(['status' => 'selesai']);
        }
    }

    // ==============================
    // 🔧 HELPER: PROSES CASHBACK
    // ==============================
    private function processCashback($transaksi, $firebases)
    {
        if ($transaksi->cashback_amount <= 0) return;

        $user = $transaksi->user;

        $saldo = SaldoKoin::firstOrCreate(
            ['user_id' => $user->id],
            ['jumlah' => 0]
        );

        $saldo->jumlah += $transaksi->cashback_amount;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id'   => $user->id,
            'jumlah'    => $transaksi->cashback_amount,
            'tipe'      => 'masuk',
            'deskripsi' => "Cashback pesanan {$transaksi->kode_pemesanan} telah masuk",
        ]);

        // Kirim notifikasi
        $fcmUser = User::with('fcmTokens')->find($user->id);
        $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

        if (!empty($tokens)) {
            $title = 'Cashback berhasil didapatkan';
            $body  = "Cashback sebanyak {$transaksi->cashback_amount} berhasil masuk ke akunmu.";

            $firebases->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'type' => 'cashback',
                    'transaksi_id' => $transaksi->id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tokens);
        }
    }

    // ==============================
    // 🔧 HELPER: KEMBALIKAN DANA REFUND + CASHBACK
    // ==============================
    private function refundBalance($transaksi, $firebases)
    {
        $user = $transaksi->user;

        $saldo = SaldoKoin::firstOrCreate(
            ['user_id' => $user->id],
            ['jumlah' => 0]
        );

        // Kembalikan total amount
        $saldo->jumlah += $transaksi->total;
        $saldo->save();

        TransaksiSaldoKoin::create([
            'user_id'   => $user->id,
            'jumlah'    => $transaksi->total,
            'tipe'      => 'masuk',
            'deskripsi' => "Pengembalian dana refund multitenant pesanan {$transaksi->kode_pemesanan}",
        ]);

        // Jika ada cashback_amount pada transaksi refund, kembalikan juga cashback-nya
        if ($transaksi->cashback_amount > 0) {
            $saldo->jumlah += $transaksi->cashback_amount;
            $saldo->save();

            TransaksiSaldoKoin::create([
                'user_id'   => $user->id,
                'jumlah'    => $transaksi->cashback_amount,
                'tipe'      => 'masuk',
                'deskripsi' => "Pengembalian cashback refund multitenant pesanan {$transaksi->kode_pemesanan}",
            ]);

            // Kirim notifikasi untuk pengembalian cashback
            $fcmUser = User::with('fcmTokens')->find($user->id);
            $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

            if (!empty($tokens)) {
                $title = 'Cashback dikembalikan';
                $body  = "Cashback sebanyak {$transaksi->cashback_amount} telah dikembalikan ke akunmu karena refund.";

                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => 'cashback_refund',
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($tokens);
            }
        }

        // Notifikasi untuk pengembalian dana utama
        $fcmUser = User::with('fcmTokens')->find($user->id);
        $tokens = $fcmUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray() ?? [];

        if (!empty($tokens)) {
            $title = 'Dana refund dikembalikan';
            $body  = "Dana sebanyak {$transaksi->total} telah dikembalikan ke saldomu karena pembatalan pesanan.";

            $firebases->withNotification($title, $body)
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'type' => 'refund',
                    'transaksi_id' => $transaksi->id,
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ])
                ->sendToFallback($tokens);
        }
    }

    private function sendNotifications($transaksi, $firebases, $request)
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
        $groupTransaksi = Transaksi::where('multitenant_id', $transaksi->multitenant_id)->get();
        $isMultiTenant = !empty($transaksi->multitenant_id);
        $stillHasPending = false;

        if ($isMultiTenant) {
            // Hitung pending hanya untuk multitenant
            $stillHasPending = $groupTransaksi
                ->where('status', 'pesanan_masuk')
                ->where('id', '!=', $transaksi->id)
                ->where('isPriority', 0)
                ->isNotEmpty();
            $stillHasNotPesananMasuk = $groupTransaksi
                ->where('status', '=', 'siap_diantar')
                ->where('id', '!=', $transaksi->id)
                ->where('isPriority', 0)
                ->isNotEmpty();
        }

        if ($transaksi->status === 'pesanan_masuk' && $request->status === 'pesanan_diproses') {
            if ($stillHasNotPesananMasuk && $isMultiTenant) {
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
        }

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

            if (!$stillHasPending || !$isMultiTenant) {
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
