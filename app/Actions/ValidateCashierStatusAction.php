<?php

namespace App\Actions;

use App\Models\Cashier;
use App\Response\ResponseApi;

class ValidateCashierStatusAction
{
    /**
     * Validasi seluruh kombinasi status cashier.
     * Return ResponseApi error jika invalid, atau null jika valid.
     */
    public function execute(Cashier $cashier, string $requestedStatus)
    {
        $current = $cashier->status;

        $rules = [
            // Sudah selesai → tidak boleh diapa-apakan
            'selesai|selesai' => 'Pesanan kasir sudah selesai sebelumnya.',
            'selesai|pesanan_diproses' => 'Pesanan kasir sudah selesai sebelumnya.',

            // Gagal bayar → tidak boleh diapa-apakan
            'gagal_bayar|selesai' => 'Pesanan kasir gagal dibayar.',
            'gagal_bayar|pesanan_diproses' => 'Pesanan kasir gagal dibayar.',
            'gagal_bayar|pending' => 'Pesanan kasir gagal dibayar.',

            // Pending tidak boleh langsung selesai
            'pending|selesai' => 'Pesanan kasir belum dibayar.',
            'pending|gagal_bayar' => 'Pesanan kasir belum dibayar.',
            'pending|pesanan_diproses' => 'Pesanan kasir belum dibayar.',

            // Sedang diproses tidak boleh kembali mundur
            'pesanan_diproses|pending' => 'Pesanan kasir sudah dalam proses sebelumnya.',
            'pesanan_diproses|gagal_bayar' => 'Pesanan kasir sudah dalam proses sebelumnya.',
            'pesanan_diproses|pesanan_diproses' => 'Pesanan kasir sudah dalam proses sebelumnya.',
        ];

        $key = "{$current}|{$requestedStatus}";

        if (isset($rules[$key])) {
            return ResponseApi::error($rules[$key], 400);
        }

        return null;
    }
}
