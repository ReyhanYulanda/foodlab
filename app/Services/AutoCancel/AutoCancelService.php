<?php

namespace App\Services\AutoCancel;

use App\Repositories\TransaksiRepository;
use App\Services\AutoCancel\Actions\ProcessExpiredTransaksiAction;
use App\Services\AutoCancel\Policies\TimeoutEligibilityPolicy;

class AutoCancelService
{
    public function __construct(
        private TransaksiRepository $transaksiRepository,
        private ProcessExpiredTransaksiAction $processor,
        private TimeoutEligibilityPolicy $timeoutPolicy,
    ) {}

    /**
     * Execute the auto cancel process.
     *
     * @return int $timeout (for logging/info)
     */
    public function execute(): int
    {
        $timeout = $this->timeoutPolicy->getTimeoutMinutes();
        $threshold = $this->timeoutPolicy->getThreshold();

        $transaksis = $this->transaksiRepository->getExpiredPesananMasuk($threshold);

        foreach ($transaksis as $transaksi) {
            $this->processor->handle($transaksi, $timeout);
        }

        return $timeout;
    }
}
