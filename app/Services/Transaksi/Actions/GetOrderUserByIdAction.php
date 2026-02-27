<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\TransaksiRepository;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;

class GetOrderUserByIdAction
{
    protected $transaksiRepo;

    public function __construct(TransaksiRepository $transaksiRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(Request $request, User $user, int $id)
    {
        $transaksi = $this->transaksiRepo->getUserOrTenantOrderById($user->id, $id);

        if (!$transaksi) {
            throw new Exception('Transaksi tidak ditemukan', 404);
        }

        // Data tambahan dari tabel checkout
        $extra = [];
        if ($transaksi->checkout) {
            $extra = [
                'order_id_midtrans' => $transaksi->checkout->midtrans_request_id,
                'qr_url' => $transaksi->checkout->kode_bayar,
                'expiry' => $transaksi->checkout->tgl_akhir_tagihan,
                'biaya_admin' => $transaksi->checkout->total_biaya_admin,
                'grand_total' => $transaksi->checkout->total_bayar_user
            ];
        }

        return [
            'transaksi' => array_merge($transaksi->toArray(), $extra),
        ];
    }
}
