<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenants;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ResetTenantBusy extends Command
{
    protected $signature = 'tenants:reset-busy';
    protected $description = 'Reset kolom is_busy tenant jika sudah lebih dari 1 jam';

    public function handle()
    {
        $tenants = Tenants::whereNotNull('is_busy')->get();

        foreach ($tenants as $tenant) {
            if (Carbon::parse($tenant->is_busy)->addHour()->isPast()) {
                $tenant->update(['is_busy' => null]);
                $this->info("Tenant {$tenant->id} sudah direset is_busy.");
            }
        }
        Log::info('Reset is_busy tenant selesai.');
        return Command::SUCCESS;
    }
}
