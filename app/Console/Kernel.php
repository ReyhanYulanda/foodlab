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
        $schedule->command('order:autocancel')->everyMinute();
        $schedule->command('tenant:update-status')->everyMinute();
        $schedule->command('tenant:tutup')->everyMinute();
        $schedule->command('tenant:reset-manual-offline')->dailyAt('22:12');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
