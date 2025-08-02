<?php

namespace App\Services\Kelola;

use App\Models\Tenants;
use App\Models\Transaksi;
use App\Response\ResponseApi;
use App\Helper\ValidationHelper;
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

        if ($validation) {
            return $validation;
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
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        $user = User::find($transaksi->user_id);

        // Pastikan token user pembeli dalam bentuk array
        $userToken = $user && $user->fcm_token ? [$user->fcm_token] : [];

        // SEND TO USER (Pembeli)
        $sendToUser = function ($title, $body, $type) use ($firebases, $transaksi, $userToken) {
            if (!empty($userToken)) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $type,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToFallback($userToken);
            }
        };

        // SEND TO TENANT
        $sendToTenant = function ($title, $body, $type) use ($firebases, $transaksi) {
            $tenantToken = optional($transaksi->tenant->user)->fcm_token;
            if ($tenantToken) {
                $firebases->withNotification($title, $body)
                    ->withData([
                        'title' => $title,
                        'body' => $body,
                        'type' => $type,
                        'transaksi_id' => $transaksi->id,
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ])
                    ->sendToTenant([$tenantToken]);
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

            $sendToDrivers(
                'Ada Pesanan Baru',
                "Pesanan {$transaksi->id} sedang diantar. Yuk, bantu antar!",
                'diantar_driver'
            );
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
