<?php

namespace App\Repositories;

use App\Models\SaldoKoin;

class SaldoKoinRepository
{
    public function firstOrCreate(int $userId, int $defaultJumlah = 0): SaldoKoin
    {
        return SaldoKoin::firstOrCreate(
            ['user_id' => $userId],
            ['jumlah' => $defaultJumlah]
        );
    }

    public function getByUserIdForUpdate(int $userId): ?SaldoKoin
    {
        return SaldoKoin::where('user_id', $userId)->lockForUpdate()->first();
    }

    public function create(array $data): SaldoKoin
    {
        return SaldoKoin::create($data);
    }
}
