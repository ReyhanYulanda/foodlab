<?php

namespace App\Repositories;

use App\Models\TransaksiSaldoKoin;

class TransaksiSaldoKoinRepository
{
    public function create(array $data): TransaksiSaldoKoin
    {
        return TransaksiSaldoKoin::create($data);
    }

    public function getPaginatedByUser(int $userId, int $perPage = 10, int $page = 1)
    {
        return TransaksiSaldoKoin::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
