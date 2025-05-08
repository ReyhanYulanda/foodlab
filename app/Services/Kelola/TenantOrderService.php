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
        $masbro = User::role('masbro')->first();

        if ($transaksi->status == 'refund_selesai') {
            $firebases->withNotification(
                'Refund Selesai',
                "Refund pesanan {$transaksi->order_id} selesai"
            )->sendMessages($transaksi->user->fcm_token);
        }

        if ($transaksi->status == 'pesanan_diproses') {
            $firebases->withNotification(
                'Pesanan Sedang Diproses',
                "Pesanan {$transaksi->order_id} sedang dibuat oleh tenant. Mohon ditunggu, ya!"
            )->sendMessages($transaksi->user->fcm_token);
        }

        if ($transaksi->status == 'siap_diantar') {
            $firebases->withNotification(
                'Pesanan Sudah Siap',
                "Pesanan {$transaksi->order_id} selesai dibuat. Kami sedang mencari driver untuk mengantar pesananmu"
            )->sendMessages($transaksi->user->fcm_token);

            $firebases->withNotification(
                'Ada Pesanan Siap Diantar',
                "Pesanan {$transaksi->order_id} sudah siap. Yuk, ambil dan antar sekarang!"
            )->sendMessages($masbro->fcm_token);
        }

        if ($transaksi->status == 'diantar') {
            $firebases->withNotification(
                'Pesanan Segera Diantar',
                "Pesanan {$transaksi->order_id} sudah mendapat driver dan akan segera diantar ke lokasimu"
            )->sendMessages($transaksi->user->fcm_token);
        }

        if ($transaksi->status == 'selesai') {
            $firebases->withNotification(
                'Pesanan Selesai',
                "Pesanan {$transaksi->order_id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽"
            )->sendMessages($transaksi->user->fcm_token);
        }
    }
}