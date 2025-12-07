<?php

namespace App\Services\Kelola\Actions;

use App\Repositories\TransaksiRepository;
use App\Response\ResponseApi;
use App\Services\Firebases;
use App\Services\Kelola\Actions\Notifications\SendOrderNotificationAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateStatusPesananAction
{
    public function __construct(
        protected TransaksiRepository $transaksiRepository,
        protected ValidateStatusTransitionAction $validateStatusTransitionAction,
        protected HandlePriorityOrderAction $handlePriorityOrderAction,
        protected HandleMultiTenantOrderAction $handleMultiTenantOrderAction,
        protected ProcessCashbackAction $processCashbackAction,
        protected SendOrderNotificationAction $sendOrderNotificationAction,
    ) {}

    public function execute(Request $request, Firebases $firebases, int $id)
    {
        $transaksi = $this->transaksiRepository->findWithUserById($id);

        if (!$transaksi) {
            return ResponseApi::error('pesanan tidak ditemukan', 404);
        }

        // Semua guard & validasi status (termasuk ValidationHelper)
        $validationResponse = $this->validateStatusTransitionAction->execute($request, $transaksi);

        if ($validationResponse) {
            return $validationResponse;
        }

        try {
            // Flow prioritas (request->status bisa berubah, notif driver)
            $this->handlePriorityOrderAction->execute($request, $firebases, $transaksi);

            // Flow multitenant (request->status bisa berubah)
            $this->handleMultiTenantOrderAction->execute($request, $transaksi);

            // Cashback (dijalankan sebelum status di-set ke selesai → sama seperti sebelumnya)
            $this->processCashbackAction->execute($request, $firebases, $transaksi);

            // Update status & simpan
            $transaksi->status = $request->status;
            $this->transaksiRepository->save($transaksi);

            if ($transaksi->metode_pembayaran != 'transfer') {
                $transaksi->listTransaksiDetail()->update(['status' => $transaksi->status]);
            }

            // Kirim notifikasi
            $this->sendOrderNotificationAction->execute($transaksi, $firebases);

            return ResponseApi::success(null, "Pesanan {$transaksi->status}");
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return ResponseApi::error($e->getMessage());
        }
    }
}