<?php

namespace App\Services\Kelola\Actions;

use App\Helper\ValidationHelper;
use App\Repositories\CashierRepository;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Illuminate\Http\Request;

class UpdateStatusPesananCashierAction
{
    public function __construct(
        protected CashierRepository $cashierRepository,
    ) {}

    public function execute(Request $request, Firebases $firebases, int $id)
    {
        $validation = ValidationHelper::validate($request->all(), [
            'status' => 'required|in:pesanan_diproses,selesai',
        ]);

        if ($validation) {
            return $validation;
        }

        $cashier = $this->cashierRepository->findWithTenantAndOwner($id);

        if (!$cashier) {
            return ResponseApi::error('Transaksi kasir tidak ditemukan', 404);
        }

        $user = $request->user();
        $tenant = $cashier->tenant;

        if (!$tenant || $tenant->user_id !== $user->id) {
            return ResponseApi::forbidden('Kamu bukan pemilik tenant ini');
        }

        if ($cashier->status === 'selesai' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir sudah selesai sebelumnya.', 400);
        }

        if ($cashier->status === 'selesai' && $request->status === 'pesnan_diproses') {
            return ResponseApi::error('Pesanan kasir sudah selesai sebelumnya.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'gagal_bayar' && $request->status === 'pending') {
            return ResponseApi::error('Pesanan kasir gagal dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'selesai') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'gagal_bayar') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pending' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir belum dibayar.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'pending') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'gagal_bayar') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        if ($cashier->status === 'pesanan_diproses' && $request->status === 'pesanan_diproses') {
            return ResponseApi::error('Pesanan kasir sudah dalam proses sebelumnya.', 400);
        }

        $cashier->status = $request->status;
        $this->cashierRepository->save($cashier);

        return ResponseApi::success(null, "Status pesanan kasir berhasil diperbarui menjadi {$cashier->status}");
    }
}
