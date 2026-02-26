<?php

namespace App\Services\Cashier\Actions;

use App\Repositories\CashierRepository;

class GetCashierHistoryByIdAction
{
    protected CashierRepository $cashierRepo;

    public function __construct(CashierRepository $cashierRepo)
    {
        $this->cashierRepo = $cashierRepo;
    }

    public function execute($id, $request)
    {
        $user = $request->user();

        $cashier = $this->cashierRepo->getHistoryByIdAndUser($id, $user->id);

        if (!$cashier) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan atau tidak memiliki akses',
            ];
        }

        // Tambahkan EXTRA field dari checkout
        $extra = [];

        if ($cashier->checkout) {
            $extra = [
                'order_id_midtrans' => $cashier->checkout->midtrans_request_id,
                'qr_url' => $cashier->checkout->kode_bayar,
                'expiry' => $cashier->checkout->tgl_akhir_tagihan,
            ];
        }

        // Merge extra ke root data cashier (tanpa ubah struktur)
        $data = array_merge($cashier->toArray(), $extra);

        return [
            'error' => false,
            'data' => $data,
            'message' => 'Berhasil mengambil detail transaksi kasir',
        ];
    }
}
