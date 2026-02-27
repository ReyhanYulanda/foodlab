<?php

namespace App\Services\Transaksi\Actions;

use App\Models\Transaksi;
use App\Services\Firebases;
use App\Services\Midtrans;
use Illuminate\Http\Request;
use Throwable;

class ProcessWebhookMidtransAction
{
    private $firebases;

    public function __construct(Firebases $firebases)
    {
        $this->firebases = $firebases;
    }

    public function execute(Request $request)
    {
        $midtrans = new Midtrans();
        $notif = $midtrans->notification();

        try {
            $transactionStatus = $notif->transaction_status;
            $order_id = $notif->order_id;

            $order_id_parts = explode('_', $order_id);
            if (count($order_id_parts) > 1) {
                $order_id = $order_id_parts[1];
            } else {
                // Not sure format, skip or take full
            }

            $transaksi = Transaksi::find($order_id);
            if (!$transaksi)
                return;

            $tenant = $transaksi->listTransaksiDetail->first()->menus->tenants->pemilik ?? null;

            if ($transactionStatus == 'settlement') {
                $transaksi->update(['status' => 'pesanan_masuk']);
                if ($tenant) {
                    $this->firebases->withNotification('Pesanan Masuk', "Ada Pesanan Masuk!")
                        ->sendMessages($tenant->fcm_token);
                }
            } else if ($transactionStatus == 'expired') {
                $transaksi->update(['status' => $transactionStatus]);
            } else if ($transactionStatus == 'cancel') {
                $transaksi->update(['status' => $transactionStatus]);
            }
        } catch (Throwable $th) {
            // Original code did dd($transaksi), we should log it instead
            \Illuminate\Support\Facades\Log::error('Webhook error: ' . $th->getMessage());
        }
    }
}
