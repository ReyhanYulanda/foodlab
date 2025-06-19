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
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        $cekDriverIsActive = User::role('masbro')
            ->where('isOnline', 1)
            ->first();

        if ($transaksi->status == 'pesanan_diproses') {
            $firebases->withData([
                'title' => 'Pesanan Sedang Diproses',
                'body' => "Pesanan {$transaksi->id} sedang dibuat oleh tenant. Mohon ditunggu, ya!"
            ])->sendMessages($transaksi->user->fcm_token);
        }

        if ($transaksi->status == 'siap_diantar') {
            $firebases->withData([
                'title' => 'Pesanan Sudah Siap',
                'body' => "Pesanan {$transaksi->id} selesai dibuat. Kami sedang mencari driver untuk mengantar pesananmu"
            ])->sendMessages($transaksi->user->fcm_token);

            $firebases->withData([
                'title' => 'Ada Pesanan Siap Diantar',
                'body' => "Pesanan {$transaksi->id} sudah siap. Yuk, ambil dan antar sekarang!"
            ])->sendMessages($masbroTokens);
        }

        if ($transaksi->status == 'siap_diambil') {
            $firebases->withData([
                'title' => 'Pesanan Sudah Siap',
                'body' => "Pesanan {$transaksi->id} selesai dibuat. Yuk ambil pesanananmu sekarang"
            ])->sendMessages($transaksi->user->fcm_token);
        }

        if ($transaksi->status == 'diantar') {
            if ($cekDriverIsActive) {
                $firebases->withData([
                    'title' => 'Pesanan Sedang Diantar',
                    'body' => "Pesanan {$transaksi->id} sudah mendapat driver dan akan segera diantar ke lokasimu"
                ])->sendMessages($transaksi->user->fcm_token);
            } else {
                return ResponseApi::error('Tidak ada driver online saat ini', 403);
            }
        }

        if ($transaksi->status == 'selesai') {
            $firebases->withData([
                'title' => 'Pesanan Selesai',
                'body' => "Pesanan {$transaksi->id} telah selesai. Ambil dan terima pesananmu. Selamat menikmati! 🍽"
            ])->sendMessages($transaksi->user->fcm_token);
        }
    }
}
