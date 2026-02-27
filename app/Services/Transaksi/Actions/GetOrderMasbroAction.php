<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\TransaksiRepository;
use Illuminate\Http\Request;
use App\Models\User;

class GetOrderMasbroAction
{
    protected $transaksiRepo;

    public function __construct(TransaksiRepository $transaksiRepo)
    {
        $this->transaksiRepo = $transaksiRepo;
    }

    public function execute(Request $request, User $user)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        return $this->transaksiRepo->getDriverOrdersPaginated($user->id, $perPage, $page);
    }
}
