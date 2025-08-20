<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\Tenants;
use App\Models\User;
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

        foreach ($tenantIds as $userId) {
            $refundCount = Transaksi::where('tenant_id', $userId)
                ->where('status', 'refund_selesai')
                ->where('updated_at', '>=', now()->subHour())
                ->count();

            $user = User::find($userId);
            $tenant = $user ? $user->tenant : null;

            Log::info("User {$userId} refund {$refundCount}x. Tenant: " . ($tenant ? $tenant->id : 'NULL'));

            if ($tenant) {
                if ($refundCount >= 2 && $tenant->is_busy === null) {
                    $tenant->update([
                        'is_busy' => now(),
                        'busy_until' => now()->addHour(),
                    ]);
                    Log::info("Tenant {$tenant->id} sudah refund >= 2x. is_busy diset ke " . now() . " busy_until: " . now()->addHour());
                }
            }
        }

        // 2. Reset tenant yang busy tapi sudah expired
        $expiredTenants = Tenants::whereNotNull('is_busy')
            ->where('busy_until', '<=', now())
            ->get();
        Log::info('Cek tenant yang busy tapi sudah expired: ' . $expiredTenants->count());
        foreach ($expiredTenants as $tenant) {
            $tenant->update([
                'is_busy' => null,
                'busy_until' => null,
            ]);
            Log::info("Tenant {$tenant->id} sudah expired. is_busy dan busy_until direset.");
        }

        Log::info('Cek tenant refund selesai.');
        return Command::SUCCESS;
    }
}
