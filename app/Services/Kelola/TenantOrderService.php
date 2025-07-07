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

        $send = function ($tokens, $title, $body, $type) use ($firebases, $transaksi) {
            $firebases->withNotification('', '')
                ->withData([
                    'title' => $title,
                    'body' => $body,
                    'type' => $type,
                    'transaksi_id' => $transaksi->id
                ])->sendMessages($tokens);
        };

        if ($transaksi->status == 'pesanan_diproses') {
            $send(
                $transaksi->user->fcm_token,
                'Pesanan Sedang Diproses',
                "Pesanan {$transaksi->id} sedang dibuat oleh tenant. Mohon ditunggu, ya!",
                'pesanan_diproses'
            );
        }

        if ($transaksi->status == 'siap_diantar') {
            $firebases->withData([
                'title' => 'Pesanan Sudah Siap',
                'body' => "Pesanan {$transaksi->id} selesai dibuat. Kami sedang mencari driver untuk mengantar pesananmu"
            ])->sendMessages($transaksi->user->fcm_token);

            foreach ($masbroTokens as $token) {
                $firebases->withData([
                    'title' => 'Ada Pesanan Siap Diantar',
                    'body' => "Pesanan {$transaksi->id} sudah siap. Yuk, ambil dan antar sekarang!"
                ])->sendMessages([$token]); // Kirim ke satu token masbro
            }
            // log response
            foreach ($masbroTokens as $token) {
                Log::info("Pesan terkirim ke masbro dengan token: $token");
            }
        }

        if ($transaksi->status == 'siap_diambil') {
            $send(
                $transaksi->user->fcm_token,
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->id} selesai dibuat. Yuk ambil pesanananmu sekarang",
                'siap_diambil'
            );
        }

        if ($transaksi->status == 'diantar') {
            $send(
                $transaksi->user->fcm_token,
                'Pesanan Sedang Diantar',
                "Pesanan {$transaksi->id} sedang diantar oleh driver. Silakan tunggu sebentar.",
                'diantar'
            );

            $send(
                $masbroTokens,
                'Ada Pesanan Baru',
                "Pesanan {$transaksi->id} sedang diantar. Yuk, bantu antar!",
                'diantar_driver'
            );
        }

        if ($transaksi->status == 'selesai') {
            $send(
                $transaksi->user->fcm_token,
                'Pesanan Selesai',
                "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽",
                'selesai'
            );
        }
    }
}
