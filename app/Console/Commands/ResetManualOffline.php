<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Tenants;
use Carbon\Carbon;

class ResetManualOffline extends Command
{
    protected $signature = 'tenant:reset-manual-offline';
    protected $description = 'Reset manual_offline ke false tiap pagi sebelum jam buka';

    public function handle()
    {

        $tenants = Tenants::with('pemilik')->get();

        foreach ($tenants as $tenant) {
            $user = $tenant->pemilik;

            if (!$user) continue;

            $jamBuka = $tenant->jam_buka;

            if (!$jamBuka) continue;

            // Reset jika sekarang < jam buka

            $user->manual_offline = false;
            $user->manual_override = false;
            $user->isOnline = 0;
            $user->save();
        }

        $this->info('manual_offline telah di-reset untuk semua tenant yang aktif hari ini.');
    }
}
