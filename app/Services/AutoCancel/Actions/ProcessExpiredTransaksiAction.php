<?php

namespace App\Services\AutoCancel\Actions;

use App\Models\Transaksi;
use App\Services\AutoCancel\Services\OrderStateTransitionService;
use App\Services\AutoCancel\Services\ExpiredOrderNotifier;
use App\Repositories\ExpiredTransactionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessExpiredTransaksiAction
{
    public function __construct(
        private OrderStateTransitionService $stateService,
        private ExpiredTransactionRepository $repository,
        private ExpiredOrderNotifier $notifier,
    ) {}

    /**
     * Process one expired transaction.
     */
    public function handle(Transaksi $transaksi, int $timeout): void
    {
        DB::beginTransaction();

        try {
            $user = $transaksi->user;
            $fcmTokens = $this->notifier->getUserFcmTokens($user);

            $this->stateService->rejectOrder($transaksi, $timeout);

            if ($transaksi->multitenant_id) {
                $allDone = $this->stateService->processMultitenantCancel($transaksi);

                $this->notifier->notifyMultitenantCancellation($transaksi, $fcmTokens);
                $this->notifier->notifyTenantCancellation($transaksi);

                if ($allDone) {
                    $this->repository->refundFullMultitenant($transaksi);
                    $this->notifier->notifyFullMultitenantRefund($transaksi, $fcmTokens);
                }

                if ($transaksi->isPriority) {
                    $this->notifier->notifyPriorityCancelToDrivers($transaksi);
                }
            } else {
                $this->repository->refundKoin($transaksi);
                $this->repository->restoreVoucher($transaksi);
                $this->stateService->completeRefund($transaksi);

                $this->notifier->notifySingleRefund($transaksi, $fcmTokens);
            }

            Log::info("Transaksi #{$transaksi->id} dibatalkan otomatis setelah $timeout menit dan refund berhasil.");
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Gagal membatalkan transaksi #{$transaksi->id}: " . $e->getMessage());
        }
    }
}
