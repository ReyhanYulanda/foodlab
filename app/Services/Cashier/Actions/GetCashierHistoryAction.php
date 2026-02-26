<?php

namespace App\Services\Cashier\Actions;

use App\Repositories\CashierRepository;

class GetCashierHistoryAction
{
    protected CashierRepository $cashierRepo;

    public function __construct(CashierRepository $cashierRepo)
    {
        $this->cashierRepo = $cashierRepo;
    }

    public function execute($request)
    {
        $user = $request->user();

        $cashiers = $this->cashierRepo->getHistoryByUser($user->id);

        if ($cashiers->isEmpty()) {
            return [
                'empty' => true,
                'data' => [],
                'message' => 'Belum ada transaksi kasir untuk tenant ini',
            ];
        }

        // Tambahkan extra field ke setiap cashier
        $cashiers = $cashiers->map(function ($c) {
            $extra = [];

            if ($c->checkout) {
                $extra = [
                    'order_id_midtrans' => $c->checkout->midtrans_request_id,
                    'qr_url' => $c->checkout->kode_bayar,
                    'expiry' => $c->checkout->tgl_akhir_tagihan,
                ];
            }

            // merge extra ke structure cashier (tidak mengubah struktur)
            return array_merge($c->toArray(), $extra);
        });

        return [
            'empty' => false,
            'data' => $cashiers,
            'message' => 'Berhasil mengambil riwayat kasir',
        ];
    }
}
