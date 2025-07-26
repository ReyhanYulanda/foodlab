<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pengaturan;
use Carbon\Carbon;
use App\Models\Tenants;
use Illuminate\Support\Facades\Log;

class TenantTutup extends Command
{
    protected $signature = 'tenant:tutup';
    protected $description = 'Set semua tenant menjadi offline pada jam tertentu';

    public function handle()
    {
        $jamTutup = Pengaturan::where('nama', 'jam_tutup_tenant')->first();

        if (!$jamTutup) {
            $this->error('Pengaturan jam_tutup_global tidak ditemukan.');
            return;
        }

        $now = Carbon::now();
        $jamSekarang = $now->format('H:i');
        $jamTutupValue = $jamTutup->nilai;

        if ($jamSekarang >= $jamTutupValue) {
            $tenants = Tenants::with('pemilik')->get();

            foreach ($tenants as $tenant) {
                $user = $tenant->pemilik;
                if ($user && !$user->manual_offline && !$user->manual_override) {
                    $user->isOnline = 0;
                    $user->save();
                }
            }

            Log::info("Command tenant:tutup dijalankan pada $jamSekarang, semua tenant diset offline.");
            $this->info("Semua tenant diset offline pada jam $jamSekarang.");
        } else {
            $this->info("Belum waktunya tutup. Sekarang: $jamSekarang, Jam tutup: $jamTutupValue");
        }
    }
}
