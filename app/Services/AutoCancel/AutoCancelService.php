<?php

namespace App\Services\AutoCancel;

use App\Repositories\TransaksiRepository;
use App\Services\AutoCancel\Actions\ProcessExpiredTransaksiAction;
use App\Models\Pengaturan;
use Carbon\Carbon;

class AutoCancelService
{
    public function __construct(
        private TransaksiRepository $transaksiRepository,
        private ProcessExpiredTransaksiAction $processor,
    ) {}

    /**
     * Jalankan proses auto cancel.
     *
     * @return int $timeout (untuk logging/info)
     */
    public function execute(): int
    {
        $timeout = Pengaturan::where('nama', 'timeout_pesanan')->value('nilai');
        $timeout = $timeout ?? 10;

        $threshold = Carbon::now()->subMinutes($timeout);

        $transaksis = $this->transaksiRepository->getExpiredPesananMasuk($threshold);

        foreach ($transaksis as $transaksi) {
            $this->processor->handle($transaksi, $timeout);
        }

        return $timeout;
    }
}
