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
        $jam_clear_chat = Pengaturan::where('nama', 'jam_clear_chat')->first();
        $jam_siap_diambil_done_otomatis = Pengaturan::where('nama', 'jam_siap_diambil_done_otomatis')->first();
        $schedule->command('order:autocancel')->everyMinute();
        $schedule->command('tenant:update-status')->everyMinute();
        $schedule->command('tenant:tutup')->everyMinute();
        $schedule->command('tenant:reset-manual-offline')->dailyAt($reset_manual->nilai ?? '00:00');
        $schedule->command('driver:tutup')->dailyAt($jam_tutup_driver->nilai ?? '00:00');
        $schedule->command('chat:delete-finished')->dailyAt($jam_clear_chat->nilai ?? '00:00');
        $schedule->command('notifikasi:siap-diantar')->everyMinute();
        $schedule->command('notifikasi:pesanan-masuk')->everyMinute();
        $schedule->command('notifikasi:pesanan-diproses')->everyMinute();
        $schedule->command('transaksi:auto-complete-siap-diambil')->dailyAt($jam_siap_diambil_done_otomatis->nilai ?? '02:00');
        $schedule->command('tenants:reset-busy')->everyMinute();
        $schedule->command('tenants:check-refund')->everyMinute();
        $schedule->command('transactions:update-failed')->everyMinute();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
