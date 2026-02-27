<?php

namespace App\Services\Transaksi\Actions;

use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class GetOnlineDriverAction
{
    protected $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    public function execute(Request $request)
    {
        $drivers = $this->userRepo->getOnlineDrivers();
        $jumlahDriver = $drivers->count();

        return [
            'jumlah_driver' => $jumlahDriver,
            'drivers' => $drivers
        ];
    }
}
