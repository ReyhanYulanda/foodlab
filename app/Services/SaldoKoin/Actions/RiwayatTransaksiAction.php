<?php

namespace App\Services\SaldoKoin\Actions;

use App\Repositories\TransaksiSaldoKoinRepository;

class RiwayatTransaksiAction
{
    protected TransaksiSaldoKoinRepository $transaksiRepo;

    public function __construct(TransaksiSaldoKoinRepository $transaksiRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(int $userId, int $perPage = 10, int $page = 1)
    {
        return $this->transaksiRepo->getPaginatedByUser($userId, $perPage, $page);
    }
}
