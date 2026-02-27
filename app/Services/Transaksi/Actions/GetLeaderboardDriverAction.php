<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\TransaksiRepository;
use Illuminate\Http\Request;

class GetLeaderboardDriverAction
{
    protected $transaksiRepo;

    public function __construct(TransaksiRepository $transaksiRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(Request $request)
    {
        $leaderboard = $this->transaksiRepo->getDriverLeaderboard();

        return $leaderboard->map(function ($item) {
            $driver = $item->driver()->first(); // akses relasi driver manual
            return [
                'nama_driver' => $driver ? $driver->name : null,
                'foto_driver' => $driver ? $driver->image : null,
                'total_transaksi' => $item->total_transaksi,
            ];
        });
    }
}
