<?php

namespace App\Services\Kelola;

use App\Actions\HandlePriorityDriverAction;
use App\Actions\ProcessCashbackAction;
use App\Actions\SendOrderNotificationsAction;
use App\Actions\UpdateCashierStatusAction;
use App\Actions\UpdateTransaksiStatusAction;
use App\Actions\ValidateCashierStatusAction;
use App\Actions\ValidateOrderStatusAction;
use App\DTO\OrderStatusUpdateDto;
use App\Helper\ValidationHelper;
use App\Models\Cashier;
use App\Repositories\TenantRepository;
use App\Repositories\TransaksiRepository;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TenantOrderService
{
    public function __construct(
        protected TransaksiRepository           $transaksiRepository,
        protected TenantRepository              $tenantRepository,
        protected ValidateOrderStatusAction     $validateOrderStatusAction,
        protected HandlePriorityDriverAction    $handlePriorityDriverAction,
        protected UpdateTransaksiStatusAction   $updateTransaksiStatusAction,
        protected ProcessCashbackAction         $processCashbackAction,
        protected SendOrderNotificationsAction  $sendOrderNotificationsAction,
        protected UpdateCashierStatusAction     $updateCashierStatusAction,
        protected ValidateCashierStatusAction   $validateCashierStatusAction,
    ) {}

    public function getDataPesanan($userId, $status = null)
    {
        try {
            $tenant   = $this->tenantRepository->findByUserId((int) $userId);
            $tenantId = $tenant?->id;

            return $this->transaksiRepository->getTenantOrders($tenantId, $status);
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function updateStatusPesanan(Request $request, Firebases $firebases, $id)
    {
        $transaksi = $this->transaksiRepository->findWithUser((int) $id);

        if (!$transaksi) {
            return ResponseApi::error('pesanan tidak ditemukan', 404);
        }

        // Validasi input (request payload)
        $validation = ValidationHelper::validate($request->all(), [
            'status' => 'required|in:pesanan_ditolak,pesanan_diproses,siap_diantar,siap_diambil,diantar,selesai',
        ]);

        if ($validation) {
            return $validation;
        }

        $dto = OrderStatusUpdateDto::fromRequest($request, (int) $id);

        // Validasi bisnis (status transition)
        if ($response = $this->validateOrderStatusAction->execute($transaksi, $dto)) {
            return $response;
        }

        // Handle logic khusus priority & multitenant (bisa mengubah status di DTO)
        $dto = $this->handlePriorityDriverAction->execute($transaksi, $dto, $firebases);

        // Proses cashback (gunakan status baru, tapi cek status lama di Transaksi)
        $this->processCashbackAction->execute($transaksi, $dto->status, $firebases);

        try {
            // Update status transaksi + detail (jika bukan transfer)
            $transaksi = $this->updateTransaksiStatusAction->execute($transaksi, $dto->status);

            // Kirim notifikasi berdasarkan status
            $this->sendOrderNotificationsAction->execute($transaksi, $firebases);

            return ResponseApi::success(null, "Pesanan {$transaksi->status}");
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return ResponseApi::error($e->getMessage());
        }
    }

    public function updateStatusPesananCashier(Request $request, Firebases $firebases, $id)
    {
        $validation = ValidationHelper::validate($request->all(), [
            'status' => 'required|in:pesanan_diproses,selesai',
        ]);

        if ($validation) {
            return $validation;
        }

        $cashier = Cashier::with('tenant.pemilik')->find($id);

        if (!$cashier) {
            return ResponseApi::error('Transaksi kasir tidak ditemukan', 404);
        }

        $user = $request->user();
        if (!$cashier->tenant || $cashier->tenant->user_id !== $user->id) {
            return ResponseApi::forbidden('Kamu bukan pemilik tenant ini');
        }

        $validate = $this->validateCashierStatusAction->execute($cashier, $request->status);
        if ($validate) {
            return $validate;
        }

        $this->updateCashierStatusAction->execute($cashier, $request->status);

        return ResponseApi::success(null, "Status pesanan kasir berhasil diperbarui menjadi {$cashier->status}");
    }
}
?>
