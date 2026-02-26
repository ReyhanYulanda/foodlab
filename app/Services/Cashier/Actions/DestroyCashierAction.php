<?php

namespace App\Services\Cashier\Actions;

use App\Repositories\CashierRepository;
use App\Repositories\CashierDetailRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DestroyCashierAction
{
    protected CashierRepository $cashierRepo;
    protected CashierDetailRepository $detailRepo;

    public function __construct(
        CashierRepository $cashierRepo,
        CashierDetailRepository $detailRepo
    ) {
        $this->cashierRepo = $cashierRepo;
        $this->detailRepo = $detailRepo;
    }

    public function execute($id, $request)
    {
        $user = $request->user();

        $cashier = $this->cashierRepo->findWithDetails($id);

        if (!$cashier) {
            return [
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan'
            ];
        }

        // Pastikan user pemilik tenant
        $tenant = optional(optional($cashier->details->first())->menu)->tenant;
        if (!$tenant || $tenant->user_id !== $user->id) {
            return [
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menghapus transaksi kasir'
            ];
        }

        DB::beginTransaction();
        try {
            // Hapus semua detail
            $this->detailRepo->deleteByCashierId($cashier->id);
            $this->cashierRepo->delete($cashier);

            DB::commit();

            return [
                'error' => false,
                'status' => 200,
                'message' => 'Transaksi kasir berhasil dihapus',
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('Gagal menghapus transaksi kasir: ' . $th->getMessage());
            return [
                'error' => true,
                'status' => 500,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ];
        }
    }
}
