<?php

namespace App\Services;

use App\Models\Transaksi;
use Carbon\Carbon;
use Midtrans\Snap;
use Throwable;

class Midtrans
{
    private $serverKey;
    private $isProduction;
    private $isSanitized;
    private $is3ds;
    private $transaksi;
    private $clientKey;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->isProduction = config('midtrans.is_production');
        $this->isSanitized = config('midtrans.is_sanitized');
        $this->is3ds = config('midtrans.is_3ds');
        $this->clientKey = config('midtrans.client_key');

        $this->__configureMidtrans();
    }

    public function __configureMidtrans()
    {
        \Midtrans\Config::$serverKey = $this->serverKey;
        \Midtrans\Config::$isProduction = $this->isProduction;
        \Midtrans\Config::$isSanitized = $this->isSanitized;
        \Midtrans\Config::$is3ds = $this->is3ds;
        \Midtrans\Config::$overrideNotifUrl = "http://masbro-canteen.me/api/order/callback";
    }

    public function notification(){
        return new \Midtrans\Notification();
    }

    public function createParams()
    {
        return [
            'payment_type' => 'qris',
            "enabled_payments" => [
                "gopay",
                "shopeepay",
                "other_qris"
            ],
            'transaction_details' => $this->getTransaksiDetail(),
            // 'item_details' => $this->getTransaksiItemsDetail(),
            'customer_details' => $this->getUserDetail()
        ];
    }

    public function getTransaksiItemsDetail()
    {
        $itemsDetail = $this->transaksi->listTransaksiDetail->map(function ($item) {
            return [
                "id" => $item->id,
                "price" => $item->harga,
                "quantity" => $item->jumlah,
                "name" => $item->menus->nama
            ];
        });
        return $itemsDetail;
    }

    public function getTransaksiDetail()
    {
        return array(
            "order_id" => $this->createIdTransaction($this->transaksi),
            'gross_amount' => $this->transaksi->total,
        );
    }

    public function getUserDetail()
    {
        return array(
            'first_name' => $this->transaksi->user->name,
            'email' => $this->transaksi->user->email,
        );
    }

    public function createSnapTransaction(Transaksi $transaksi)
    {
        $this->transaksi = $transaksi;
        $params = $this->createParams();
        $snapTransaksi = Snap::createTransaction($params);
        return $snapTransaksi;
    }

    public function refundTransaction(Transaksi $transaksi){
        $params = array(
            'refund_key' => "$transaksi->id-refund",
            'amount' => $transaksi->total,
            'reason' => 'Pesanan Ditolak'
        );
        $refund = \Midtrans\Transaction::refund($this->createIdTransaction($transaksi), $params);
        return $refund;
    }

    public function getDateNow(){
        return date("Y-m-d");
    }

    public function cancelTransaction($id){
        try{
            $transaksi = Transaksi::find($id);
            $orderId = $this->createIdTransaction($transaksi);

            $canceled = \Midtrans\Transaction::cancel($orderId);

            return $canceled;
        }catch(Throwable $th){
            return false;
        }
    }

    public function createIdTransaction($transaksi){
        $unixtimes = Carbon::createFromFormat('Y-m-d H:i:s', $transaksi->created_at)->timestamp;
        $id = $transaksi->id;
        return $unixtimes."_".$id;
    }
}
