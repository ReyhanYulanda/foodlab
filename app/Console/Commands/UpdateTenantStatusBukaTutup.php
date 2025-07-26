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
        $now = Carbon::now()->format('H:i');

        $tenants = Tenants::with('pemilik')->get();

        foreach ($tenants as $tenant) {
            $user = $tenant->pemilik;

            if (!$user) continue;

            if ($user->manual_offline || $user->manual_override) continue;

            $jamBuka = $tenant->jam_buka;
            $jamTutup = $tenant->jam_tutup;

            if (!$jamBuka || !$jamTutup) continue;

            // Jika tidak manual offline, maka update berdasarkan jam buka
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
