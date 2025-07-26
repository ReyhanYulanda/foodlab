<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants;
use Carbon\Carbon;

class UpdateTenantStatusBukaTutup extends Command
{
    protected $signature = 'tenant:update-status';
    protected $description = 'Update status isOnline user berdasarkan jam buka dan tutup tenant';

    public function handle()
    {
        $now = \Carbon\Carbon::now()->format('H:i');

        $tenants = \App\Models\Tenants::with('pemilik')->get();

        foreach ($tenants as $tenant) {
            $user = $tenant->pemilik;

            if (!$user) continue;

            // Jika manual_offline bernilai true, maka kita tidak override status online-nya
            if ($user->manual_offline) {
                // lewati update status isOnline
                continue;
            }

            $jamBuka = $tenant->jam_buka;
            $jamTutup = $tenant->jam_tutup;

            if (is_null($jamBuka) || is_null($jamTutup)) continue;

            // Default: update status online berdasarkan jam operasional
            if ($jamBuka <= $now && $now <= $jamTutup) {
                $user->isOnline = 1;
            } else {
                $user->isOnline = 0;
            }

            $user->save();
        }

        $this->info('Status tenant berhasil diperbarui.');
    }
}
