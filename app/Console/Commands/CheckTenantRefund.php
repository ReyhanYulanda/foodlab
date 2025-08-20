<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\Tenants;
use Illuminate\Support\Facades\Log;

class CheckTenantRefund extends Command
{
    protected $signature = 'tenants:check-refund';
    protected $description = 'Cek tenant yang refund 2 kali dalam 1 jam terakhir, lalu set is_busy';

    public function handle()
    {
        $tenantIds = Transaksi::where('status', 'refund_selesai')
            ->where('updated_at', '>=', now()->subHour())
            ->pluck('tenant_id')
            ->unique();

        foreach ($tenantIds as $tenantId) {
            $refundCount = Transaksi::where('tenant_id', $tenantId)
                ->where('status', 'refund_selesai')
                ->where('updated_at', '>=', now()->subHour())
                ->count();

            if ($refundCount >= 2) {
                $tenant = Tenants::find($tenantId);
                if ($tenant && $tenant->is_busy === null) {
                    $tenant->update(['is_busy' => now()]);
                    Log::info("Tenant {$tenantId} sudah refund >= 2 kali dalam 1 jam. is_busy diset ke " . now());
                }
            }
        }
        Log::info('Cek tenant refund selesai.');
        return Command::SUCCESS;
    }
}
