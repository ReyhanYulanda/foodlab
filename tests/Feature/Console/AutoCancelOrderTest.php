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
use App\Services\Firebases;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class AutoCancelOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a mock Firebases so the command never tries real FCM calls
        $mockFirebases = Mockery::mock(Firebases::class);
        $mockFirebases->shouldReceive('withNotification')->andReturnSelf();
        $mockFirebases->shouldReceive('withData')->andReturnSelf();
        $mockFirebases->shouldReceive('sendToFallback')->andReturn(null);
        $mockFirebases->shouldReceive('sendToDriver')->andReturn(null);
        $this->app->instance(Firebases::class, $mockFirebases);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private function createTenantAndMenu(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenants::create([
            'nama_tenant' => 'Tenant ' . $owner->id,
            'nama_kavling' => 'Kavling ' . $owner->id,
            'nama_gambar' => null,
            'jam_buka' => '08:00',
            'jam_tutup' => '22:00',
            'user_id' => $owner->id,
        ]);

        $menu = Menus::create([
            'nama' => 'Menu Test',
            'harga' => 15000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ]);

        return [$owner, $tenant, $menu];
    }

    private function createOrder(User $buyer, User $owner, $menu, array $overrides = []): Transaksi
    {
        $transaksi = Transaksi::create(array_merge([
            'user_id' => $buyer->id,
            'status' => 'pesanan_masuk',
            'total' => 15000,
            'ongkos_kirim' => 0,
            'biaya_layanan' => 0,
            'isAntar' => false,
            'isPriority' => false,
            'metode_pembayaran' => 'koin',
            'tenant_id' => $owner->id,
        ], $overrides));

        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => 15000,
        ]);

        return $transaksi;
    }

    private function runAutoCancelCommand(int $timeoutMinutes = 10)
    {
        $this->artisan('order:autocancel')
            ->expectsOutput("Auto cancel executed with timeout {$timeoutMinutes} minutes.")
            ->assertExitCode(0);
    }

    /* ============================================================
     * Command-level tests
     * ============================================================ */

    /** @test */
    public function command_has_correct_signature()
    {
        $this->artisan('order:autocancel')
            ->assertSuccessful();
    }

    /** @test */
    public function command_outputs_success_message()
    {
        Pengaturan::create(['nama' => 'timeout_pesanan', 'nilai' => 10]);

        $this->runAutoCancelCommand(10);
    }

    /* ============================================================
     * Integration tests
     * ============================================================ */

    /** @test */
    public function order_pesanan_masuk_gets_auto_cancelled_after_timeout()
    {
        [$owner, $tenant, $menu] = $this->createTenantAndMenu();
        $buyer = User::factory()->create();

        Pengaturan::create(['nama' => 'timeout_pesanan', 'nilai' => 10]);

        $transaksi = $this->createOrder($buyer, $owner, $menu);

        // Simulate order was updated 15 minutes ago (beyond 10 min timeout)
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_masuk',
        ]);

        $this->runAutoCancelCommand(10);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'refund_selesai',
        ]);

        $this->assertDatabaseHas('saldo_koin', [
            'user_id' => $buyer->id,
            'jumlah' => 15000,
        ]);

        $this->assertDatabaseHas('transaksi_saldo_koin', [
            'user_id' => $buyer->id,
            'jumlah' => 15000,
            'tipe' => 'masuk',
        ]);
    }

    /** @test */
    public function order_within_timeout_is_not_cancelled()
    {
        [$owner, $tenant, $menu] = $this->createTenantAndMenu();
        $buyer = User::factory()->create();

        Pengaturan::create(['nama' => 'timeout_pesanan', 'nilai' => 10]);

        $transaksi = $this->createOrder($buyer, $owner, $menu);

        // updated_at is only 2 minutes ago (within 10 min timeout)
        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(2),
        ]);

        $this->runAutoCancelCommand(10);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_masuk',
        ]);

        $this->assertDatabaseMissing('saldo_koin', [
            'user_id' => $buyer->id,
        ]);
    }

    /** @test */
    public function order_with_other_status_is_not_cancelled()
    {
        [$owner, $tenant, $menu] = $this->createTenantAndMenu();
        $buyer = User::factory()->create();

        Pengaturan::create(['nama' => 'timeout_pesanan', 'nilai' => 10]);

        $transaksi = $this->createOrder($buyer, $owner, $menu, ['status' => 'pesanan_diproses']);

        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        $this->runAutoCancelCommand(10);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'pesanan_diproses',
        ]);
    }

    /** @test */
    public function autocancel_uses_default_timeout_when_not_configured()
    {
        [$owner, $tenant, $menu] = $this->createTenantAndMenu();
        $buyer = User::factory()->create();

        // NO Pengaturan record → default 10 minutes

        $transaksi = $this->createOrder($buyer, $owner, $menu);

        Transaksi::where('id', $transaksi->id)->update([
            'updated_at' => Carbon::now()->subMinutes(15),
        ]);

        $this->runAutoCancelCommand(10);

        $this->assertDatabaseHas('transaksi', [
            'id' => $transaksi->id,
            'status' => 'refund_selesai',
        ]);

        $this->assertDatabaseHas('saldo_koin', [
            'user_id' => $buyer->id,
            'jumlah' => 15000,
        ]);
    }
}
