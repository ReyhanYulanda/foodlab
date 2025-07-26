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

            if (!$user || $user->manual_offline) {
                continue;
            }

            $jamBuka = $tenant->jam_buka;
            $jamTutup = $tenant->jam_tutup;

            if (is_null($jamBuka) || is_null($jamTutup)) continue;

            if ($jamBuka <= $jamTutup) {
                // Normal case: buka dan tutup di hari yang sama
                $isOpen = $jamBuka <= $now && $now <= $jamTutup;
            } else {
                // Special case: jam tutup lewat tengah malam
                $isOpen = $now >= $jamBuka || $now <= $jamTutup;
            }

            $user->isOnline = $isOpen ? 1 : 0;
            $user->save();
        }

        $this->info('Status tenant berhasil diperbarui.');
    }
}
