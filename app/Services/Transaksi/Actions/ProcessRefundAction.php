<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Transaksi;
use App\Services\Midtrans;
use App\Response\ResponseApi;
use Exception;

class ProcessRefundAction
{
    public function execute(Transaksi $transaksi)
    {
        try {
            $midtrans = new Midtrans();

            $refund = $midtrans->refundTransaction($transaksi);

            return ResponseApi::success(null, $refund["status_message"]);
        } catch (Exception $e) {
            return response()->json([
                "status" => "failed",
                "message" => "Refund Gagal, Silahkan Coba Lagi Nanti",
            ]);
        }
    }
}
