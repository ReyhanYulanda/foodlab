<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AutoCancel\AutoCancelService;

class AutoCancelOrder extends Command
{
    protected $signature = 'order:autocancel';
    protected $description = 'Batalkan otomatis pesanan_masuk setelah waktu tertentu dari pengaturan';

    public function handle(AutoCancelService $service)
    {
        $service->execute();
        $this->info("Auto cancel executed.");
    }
}
