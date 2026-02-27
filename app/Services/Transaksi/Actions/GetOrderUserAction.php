<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\TransaksiRepository;
use Illuminate\Http\Request;
use App\Models\User;

class GetOrderUserAction
{
    protected $transaksiRepo;

    public function __construct(TransaksiRepository $transaksiRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(Request $request, User $user)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $transaksi = $this->transaksiRepo->getUserOrdersPaginated($user->id, $perPage, $page);

        // mapping biar ada merge dari checkout
        $transaksi->getCollection()->transform(function ($item) {
            $checkout = $item->checkout;

            $item->midtrans_request_id = $checkout->midtrans_request_id ?? null;
            $item->qr_url = $checkout->kode_bayar ?? null;
            $item->expiry = $checkout->tgl_akhir_tagihan ?? null;
            $item->biaya_admin = $checkout->total_biaya_admin ?? null;

            return $item;
        });

        return $transaksi;
    }
}
