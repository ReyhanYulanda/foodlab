<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\Tenants;
use App\Models\User;
use App\Services\Firebases;
use Illuminate\Support\Facades\Log;

class CheckTenantRefund extends Command
{
    protected $signature = 'tenants:check-refund';
    protected $description = 'Cek tenant yang refund 2 kali dalam 1 jam terakhir (atau sejak interupt), lalu set is_busy dan busy_until. Jika >=5x, user dimatikan.';

    public function handle()
    {
        $firebases = new Firebases();
        // 1. Cari tenant yang refund otomatis dalam 1 jam terakhir
        $tenantIds = Transaksi::where('status', 'refund_selesai')
            ->where('catatan_penolakan', 'like', '%otomatis%')
            ->where('updated_at', '>=', now()->subHour())
            ->pluck('tenant_id')
            ->unique();

        foreach ($tenantIds as $userId) {
            $user   = User::find($userId);
            $tenant = $user ? $user->tenant : null;

            if (!$tenant) {
                continue;
            }

            // Hitung refundCount: kalau ada is_interupt, mulai hitung dari sana.
            $refundQuery = Transaksi::where('tenant_id', $userId)
                ->where('status', 'refund_selesai')
                ->where('catatan_penolakan', 'like', '%otomatis%');

            if ($tenant->is_interupt) {
                $refundQuery->where('updated_at', '>=', $tenant->is_interupt);
            } else {
                $refundQuery->where('updated_at', '>=', now()->subHour());
            }

            $refundCount = $refundQuery->count();

            Log::info("User {$userId} refund {$refundCount}x (otomatis). Tenant: {$tenant->id}");

            // Jika refund >= 2 → warning (is_busy)
            if ($refundCount >= 2 && $tenant->is_busy === null) {
                $tenant->update([
                    'is_busy'    => now(),
                    'busy_until' => now()->addHour(),
                ]);
                Log::info("Tenant {$tenant->id} refund >= 2x. is_busy diset ke " . now() . " busy_until: " . now()->addHour());
                if ($tenant->pemilik) {
                    $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);

                    $fcmTenantTokens = $pemilikUser
                        ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                        : [];

                    if (!empty($fcmTenantTokens)) {
                        $firebases
                            ->withNotification(
                                'Tenant Sibuk',
                                'Buka aplikasi agar tenant anda tidak sibuk.'
                            )
                            ->withData([
                                'title' => 'Tenant Sibuk',
                                'body'  => 'Buka aplikasi agar tenant anda tidak sibuk.',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ])
                            ->sendToTenant($fcmTenantTokens);
                        Log::info("Tenant {$tenant->id} refund >= 2x. Notifikasi dikirim ke user_id {$pemilikUser->id}.");
                    }
                }
            }

            // Jika refund >= 5 → paksa user offline
            if ($refundCount >= 5 && $tenant->is_busy !== null && $user->isOnline == 1) {
                $user->isOnline = 0;
                $user->save();
                Log::info("Tenant {$tenant->id} refund >= 5x. User {$user->id} dipaksa offline.");
                if ($tenant->pemilik) {
                    $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);

                    $fcmTenantTokens = $pemilikUser
                        ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                        : [];

                    if (!empty($fcmTenantTokens)) {
                        $firebases
                            ->withNotification(
                                'Tenant Sibuk',
                                'Buka aplikasi agar tenant anda tidak sibuk.'
                            )
                            ->withData([
                                'title' => 'Tenant Sibuk',
                                'body'  => 'Buka aplikasi agar tenant anda tidak sibuk.',
                                'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                            ])
                            ->sendToTenant($fcmTenantTokens);
                        Log::info("Tenant {$tenant->id} refund >= 5x. Notifikasi dikirim ke user_id {$pemilikUser->id}. Tenant akan dipaksa offline.");
                    }
                }
            }
        }

        // 2. Reset tenant yang busy tapi sudah expired
        $expiredTenants = Tenants::whereNotNull('busy_until')
            ->where('busy_until', '<=', now())
            ->get();

        foreach ($expiredTenants as $tenant) {
            Log::info("Tenant {$tenant->id} busy_until expired. Reset is_busy dan busy_until.");
            $tenant->update([
                'is_busy'    => null,
                'busy_until' => null,
            ]);

            if ($tenant->pemilik) {
                $pemilikUser = User::with('fcmTokens')->find($tenant->pemilik->id);

                $fcmTenantTokens = $pemilikUser
                    ? $pemilikUser->fcmTokens->pluck('fcm_token')->filter()->unique()->toArray()
                    : [];

                if (!empty($fcmTenantTokens)) {
                    $firebases
                        ->withNotification(
                            'Tenant sudah tidak sibuk',
                            'Status sibuk akan terganti menjadi buka.'
                        )
                        ->withData([
                            'title' => 'Tenant sudah tidak sibuk',
                            'body'  => 'Status sibuk akan terganti menjadi buka.',
                            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                        ])
                        ->sendToFallback($fcmTenantTokens);
                    Log::info("Tenant {$tenant->id} busy_until expired. Notifikasi dikirim ke user_id {$pemilikUser->id}.");
                }
            }
        }

        return Command::SUCCESS;
    }
}
