<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Pengaturan;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        $reset_manual = Pengaturan::where('nama', 'reset_manual')->first();
        $jam_tutup_driver = Pengaturan::where('nama', 'jam_tutup_driver')->first();
        $schedule->command('order:autocancel')->everyMinute();
        $schedule->command('tenant:update-status')->everyMinute();
        $schedule->command('tenant:tutup')->everyMinute();
        $schedule->command('tenant:reset-manual-offline')->dailyAt($reset_manual->nilai ?? '00:00');
        $schedule->command('driver:tutup')->dailyAt($jam_tutup_driver->nilai ?? '00:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
