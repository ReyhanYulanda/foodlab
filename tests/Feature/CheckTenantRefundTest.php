<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenants;
use App\Models\Transaksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\Command;

class CheckTenantRefundTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: When refundCount >= 5, tenant is_busy is NOT null,
     * and user isOnline == 1, the user should be forced offline.
     */
    public function test_user_forced_offline_when_refund_count_gte_5()
    {
        // Create a user who is ONLINE
        $user = User::factory()->create([
            'isOnline' => 1,
            'manual_offline' => false,
            'manual_override' => false,
        ]);

        // Create a tenant linked to this user, with is_busy already set (not null)
        $tenant = Tenants::create([
            'nama_tenant' => 'Test Tenant',
            'nama_kavling' => 'A1',
            'user_id' => $user->id,
            'is_busy' => now()->subMinutes(30),
            'busy_until' => now()->addMinutes(30),
            'is_interupt' => null,
        ]);

        // Create 5 "refund_selesai" transactions with "otomatis" in catatan_penolakan
        // All within the last hour so they are picked up by the query
        for ($i = 0; $i < 5; $i++) {
            Transaksi::create([
                'user_id' => $user->id,
                'tenant_id' => $user->id,
                'status' => 'refund_selesai',
                'catatan_penolakan' => 'Refund otomatis oleh sistem',
                'total' => 10000,
                'isAntar' => false,
                'metode_pembayaran' => 'koin',
                'updated_at' => now()->subMinutes(rand(1, 50)),
            ]);
        }

        // Run the artisan command
        $this->artisan('tenants:check-refund')
            ->assertExitCode(Command::SUCCESS);

        // Refresh the user from DB and assert user was forced offline
        $user->refresh();

        $this->assertEquals(0, $user->isOnline, 'User should be forced offline (isOnline = 0)');
        $this->assertTrue((bool) $user->manual_offline, 'manual_offline should be true');
        $this->assertTrue((bool) $user->manual_override, 'manual_override should be true');
    }
}
