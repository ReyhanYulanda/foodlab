<?php

namespace Tests\Feature\Console;

use App\Models\Menus;
use App\Models\Pengaturan;
use App\Models\SaldoKoin;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use App\Services\AutoCancel\AutoCancelService;
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class AutoCancelOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===============================
    // Unit Tests (mocked service)
    // ===============================

    /**
     * Test command executes service and displays info message
     */
    public function test_command_executes_service_successfully()
    {
        $mockService = Mockery::mock(AutoCancelService::class);
        $mockService->shouldReceive('execute')
            ->once()
            ->andReturn(10);

        $this->app->instance(AutoCancelService::class, $mockService);

        $this->artisan('order:autocancel')
            ->expectsOutput('Auto cancel executed.')
            ->assertExitCode(0);
    }

    /**
     * Test command calls service execute exactly once
     */
    public function test_command_calls_service_execute_once()
    {
        $mockService = Mockery::mock(AutoCancelService::class);
        $mockService->shouldReceive('execute')
            ->once()
            ->andReturn(5);

        $this->app->instance(AutoCancelService::class, $mockService);

        $this->artisan('order:autocancel')
            ->assertExitCode(0);
    }

    /**
     * Test command has correct signature
     */
    public function test_command_has_correct_signature()
    {
        $mockService = Mockery::mock(AutoCancelService::class);
        $mockService->shouldReceive('execute')->andReturn(10);
        $this->app->instance(AutoCancelService::class, $mockService);

        $this->artisan('order:autocancel')
            ->assertExitCode(0);
    }

    /**
     * Test command handles service exception gracefully
     */
    public function test_command_propagates_service_exception()
    {
        $mockService = Mockery::mock(AutoCancelService::class);
        $mockService->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('Database connection failed'));

        $this->app->instance(AutoCancelService::class, $mockService);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $this->artisan('order:autocancel');
    }

    // =======================================
    // Integration Test: Order → AutoCancel
    // =======================================

    /**
     * Test: Create a transaksi with status pesanan_masuk,
     * simulate time passes beyond timeout, then run autocancel.
     * The order should be cancelled and koin refunded.
     */
    public function test_order_pesanan_masuk_gets_auto_cancelled_after_timeout()
    {
        // Mock Firebases to prevent real FCM calls
        $mockFirebases = Mockery::mock(Firebases::class);
        $mockFirebases->shouldReceive('withNotification')->andReturnSelf();
        $mockFirebases->shouldReceive('withData')->andReturnSelf();
        $mockFirebases->shouldReceive('sendToFallback')->andReturn(null);
        $mockFirebases->shouldReceive('sendToDriver')->andReturn(null);
        $this->app->instance(Firebases::class, $mockFirebases);

        // 1. Setup: Create user, tenant, menu
        $buyerUser = User::factory()->create(['isOnline' => true]);
        $tenantOwner = User::factory()->create(['isOnline' => true]);
        $tenant = Tenants::factory()->create([
            'user_id' => $tenantOwner->id,
        ]);
        $menu = Menus::create([
            'nama' => 'Nasi Goreng',
            'harga' => 15000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ]);

        // 2. Setup: Create timeout setting (10 minutes)
        Pengaturan::create([
            'nama' => 'timeout_pesanan',
            'nilai' => 10,
        ]);

        // 3. Create a transaksi with status 'pesanan_masuk'
        $orderTotal = 15000;
        $transaksi = Transaksi::create([
            'user_id' => $buyerUser->id,
            'status' => 'pesanan_masuk',
            'total' => $orderTotal,
            'ongkos_kirim' => 0,
            'isAntar' => false,
            'isPriority' => false,
            'metode_pembayaran' => 'koin',
            'tenant_id' => $tenantOwner->id,
            'catatan' => 'Test order',
        ]);

        // Create transaksi detail
        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => 15000,
        ]);

        // 4. Verify the order exists with pesanan_masuk
        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_masuk',
        ]);

        // 5. Simulate time: set updated_at to 15 minutes ago (beyond 10 min timeout)
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        // 6. Run the autocancel command
        $this->artisan('order:autocancel')
            ->expectsOutput('Auto cancel executed.')
            ->assertExitCode(0);

        // 7. Assert: The order should now be 'refund_selesai'
        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'refund_selesai',
        ]);

        // 8. Assert: Koin should be refunded to user's saldo
        $this->assertDatabaseHas('saldo_koin', [
            'user_id' => $buyerUser->id,
            'jumlah' => $orderTotal,
        ]);

        // 9. Assert: TransaksiSaldoKoin record should be created
        $this->assertDatabaseHas('transaksi_saldo_koin', [
            'user_id' => $buyerUser->id,
            'jumlah' => $orderTotal,
            'tipe' => 'masuk',
        ]);
    }

    /**
     * Test: A transaksi with pesanan_masuk but within timeout should NOT be cancelled.
     */
    public function test_order_within_timeout_is_not_cancelled()
    {
        // Mock Firebases
        $mockFirebases = Mockery::mock(Firebases::class);
        $mockFirebases->shouldReceive('withNotification')->andReturnSelf();
        $mockFirebases->shouldReceive('withData')->andReturnSelf();
        $mockFirebases->shouldReceive('sendToFallback')->andReturn(null);
        $this->app->instance(Firebases::class, $mockFirebases);

        // Setup
        $buyerUser = User::factory()->create(['isOnline' => true]);
        $tenantOwner = User::factory()->create(['isOnline' => true]);
        $tenant = Tenants::factory()->create([
            'user_id' => $tenantOwner->id,
        ]);
        $menu = Menus::create([
            'nama' => 'Mie Ayam',
            'harga' => 12000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ]);

        Pengaturan::create([
            'nama' => 'timeout_pesanan',
            'nilai' => 10,
        ]);

        // Create order — updated_at is "now" (within timeout)
        $transaksi = Transaksi::create([
            'user_id' => $buyerUser->id,
            'status' => 'pesanan_masuk',
            'total' => 12000,
            'ongkos_kirim' => 0,
            'isAntar' => false,
            'isPriority' => false,
            'metode_pembayaran' => 'koin',
            'tenant_id' => $tenantOwner->id,
        ]);

        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => 12000,
        ]);

        // updated_at is now (only 2 minutes ago, within 10 min timeout)
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(2),
        ]);

        // Run autocancel
        $this->artisan('order:autocancel')
            ->expectsOutput('Auto cancel executed.')
            ->assertExitCode(0);

        // Assert: order should still be pesanan_masuk
        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_masuk',
        ]);

        // Assert: No saldo_koin refund should exist
        $this->assertDatabaseMissing('saldo_koin', [
            'user_id' => $buyerUser->id,
        ]);
    }

    /**
     * Test: Orders with non-pesanan_masuk status should NOT be auto-cancelled.
     */
    public function test_order_with_other_status_is_not_cancelled()
    {
        $mockFirebases = Mockery::mock(Firebases::class);
        $mockFirebases->shouldReceive('withNotification')->andReturnSelf();
        $mockFirebases->shouldReceive('withData')->andReturnSelf();
        $mockFirebases->shouldReceive('sendToFallback')->andReturn(null);
        $this->app->instance(Firebases::class, $mockFirebases);

        $buyerUser = User::factory()->create(['isOnline' => true]);
        $tenantOwner = User::factory()->create(['isOnline' => true]);
        $tenant = Tenants::factory()->create([
            'user_id' => $tenantOwner->id,
        ]);
        $menu = Menus::create([
            'nama' => 'Es Teh',
            'harga' => 5000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ]);

        Pengaturan::create([
            'nama' => 'timeout_pesanan',
            'nilai' => 10,
        ]);

        // Create order with 'pesanan_diproses' status (already accepted by tenant)
        $transaksi = Transaksi::create([
            'user_id' => $buyerUser->id,
            'status' => 'pesanan_diproses',
            'total' => 5000,
            'ongkos_kirim' => 0,
            'isAntar' => false,
            'isPriority' => false,
            'metode_pembayaran' => 'koin',
            'tenant_id' => $tenantOwner->id,
        ]);

        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => 5000,
        ]);

        // Even if updated_at is old
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        // Run autocancel
        $this->artisan('order:autocancel')
            ->expectsOutput('Auto cancel executed.')
            ->assertExitCode(0);

        // Assert: still pesanan_diproses, not cancelled
        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_diproses',
        ]);
    }

    /**
     * Test: Default timeout (10 minutes) is used when Pengaturan has no timeout_pesanan setting.
     */
    public function test_autocancel_uses_default_timeout_when_not_configured()
    {
        $mockFirebases = Mockery::mock(Firebases::class);
        $mockFirebases->shouldReceive('withNotification')->andReturnSelf();
        $mockFirebases->shouldReceive('withData')->andReturnSelf();
        $mockFirebases->shouldReceive('sendToFallback')->andReturn(null);
        $this->app->instance(Firebases::class, $mockFirebases);

        $buyerUser = User::factory()->create(['isOnline' => true]);
        $tenantOwner = User::factory()->create(['isOnline' => true]);
        $tenant = Tenants::factory()->create([
            'user_id' => $tenantOwner->id,
        ]);
        $menu = Menus::create([
            'nama' => 'Bakso',
            'harga' => 20000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ]);

        // NO Pengaturan record → should default to 10 minutes

        $transaksi = Transaksi::create([
            'user_id' => $buyerUser->id,
            'status' => 'pesanan_masuk',
            'total' => 20000,
            'ongkos_kirim' => 0,
            'isAntar' => false,
            'isPriority' => false,
            'metode_pembayaran' => 'koin',
            'tenant_id' => $tenantOwner->id,
        ]);

        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => 20000,
        ]);

        // Set updated_at to 15 minutes ago (beyond default 10 min)
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->artisan('order:autocancel')
            ->expectsOutput('Auto cancel executed.')
            ->assertExitCode(0);

        // Should be auto-cancelled using default timeout
        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'refund_selesai',
        ]);

        $this->assertDatabaseHas('saldo_koin', [
            'user_id' => $buyerUser->id,
            'jumlah' => 20000,
        ]);
    }
}
