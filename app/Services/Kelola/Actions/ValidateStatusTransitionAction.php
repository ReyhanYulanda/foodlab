<?php

namespace App\Services\Kelola\Actions;

use App\Helper\ValidationHelper;
use App\Response\ResponseApi;
use Illuminate\Http\Request;

class ValidateStatusTransitionAction
{
    public function execute(Request $request, $transaksi)
    {
        // Sama persis seperti logic awal (urutannya dipertahankan)

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

        return null;
    }
}
