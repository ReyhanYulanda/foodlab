<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pengaturan;
use Carbon\Carbon;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DriverTutup extends Command
{
    protected $signature = 'driver:tutup';
    protected $description = 'Set semua driver menjadi offline pada jam 00:00';

    public function handle()
    {
        $jamTutup = Pengaturan::where('nama', 'jam_tutup_driver')->first();

        if (!$jamTutup) {
            $this->error('Pengaturan jam_tutup_driver tidak ditemukan.');
            return;
        }

        $now = Carbon::now();
        $jamSekarang = $now->format('H:i');
        $jamTutupValue = $jamTutup->nilai;

        if ($jamSekarang >= $jamTutupValue) {
            $drivers = User::role('masbro')->where('isOnline', 1)->get();

            foreach ($drivers as $driver) {
                $driver->update([
                    'isOnline' => 0
                ]);
            }
            Log::info("Command driver:tutup dijalankan pada $jamSekarang, semua driver diset offline.");
            $this->info("Semua driver diset offline pada jam $jamSekarang.");
        } else {
            $this->info("Belum waktunya tutup. Sekarang: $jamSekarang, Jam tutup: $jamTutupValue");
        }
    }
}
