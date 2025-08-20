<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\Tenants;
use Illuminate\Support\Facades\Log;

class CheckTenantRefund extends Command
{
    protected $signature = 'tenants:check-refund';
    protected $description = 'Cek tenant yang refund 2 kali dalam 1 jam terakhir, lalu set is_busy dan busy_until';

    public function handle()
    {
        // 1. Cari tenant yang refund dalam 1 jam terakhir
        $tenantIds = Transaksi::where('status', 'refund_selesai')
            ->where('updated_at', '>=', now()->subHour())
            ->pluck('tenant_id')
            ->unique();
        Log::info('Cek tenant refund dimulai. Tenant yang refund dalam 1 jam terakhir: ' . $tenantIds->count());

        foreach ($tenantIds as $tenantId) {
            $refundCount = Transaksi::where('tenant_id', $tenantId)
                ->where('status', 'refund_selesai')
                ->where('updated_at', '>=', now()->subHour())
                ->count();
            Log::info("Tenant {$tenantId} memiliki {$refundCount} refund dalam 1 jam terakhir.");

            $tenant = Tenants::find($tenantId);

            if ($tenant) {
                // Kalau refund >= 2x → set busy
                if ($refundCount >= 2 && $tenant->is_busy === null) {
                    $tenant->update([
                        'is_busy' => now(),
                        'busy_until' => now()->addHour(), // expired setelah 1 jam
                    ]);
                    $tenant->save();
                    Log::info("Tenant {$tenantId} sudah refund >= 2 kali dalam 1 jam. is_busy diset ke " . now() . " busy_until: " . now()->addHour());
                }
            }
        }

        // 2. Reset tenant yang busy tapi sudah expired
        $expiredTenants = Tenants::whereNotNull('is_busy')
            ->where('busy_until', '<=', now())
            ->get();

        foreach ($expiredTenants as $tenant) {
            $tenant->update([
                'is_busy' => null,
                'busy_until' => null,
            ]);
            $tenant->save();
            Log::info("Tenant {$tenant->id} busy_until sudah lewat. Reset is_busy.");
        }

        Log::info('Cek tenant refund selesai.');
        return Command::SUCCESS;
    }
}
