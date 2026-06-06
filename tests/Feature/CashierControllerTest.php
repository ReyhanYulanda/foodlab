<?php

namespace Tests\Feature;

use App\Models\Cashier;
use App\Models\CashierDetail;
use App\Models\Menus;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middlewares\RoleMiddleware;
use Tests\TestCase;

class CashierControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set dummy Midtrans config so the controller doesn't crash on missing keys
        Config::set('custom.midtrans_server_key', 'dummy-server-key');
        Config::set('custom.midtrans_is_production', false);
        Config::set('custom.midtrans_is_sanitized', true);
        Config::set('custom.midtrans_is_3ds', true);

        // Bypass Spatie role middleware so we don't need to seed roles in every test
        $this->withoutMiddleware(RoleMiddleware::class);
    }

    protected function tearDown(): void
    {
        if (class_exists(\Midtrans\MT_Tests::class)) {
            \Midtrans\MT_Tests::reset();
        }

        parent::tearDown();
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private function createTenantUser(array $overrides = []): User
    {
        $user = User::factory()->create($overrides);

        Tenants::create([
            'nama_tenant' => 'Tenant ' . $user->id,
            'nama_kavling' => 'Kavling ' . $user->id,
            'nama_gambar' => null,
            'jam_buka' => '08:00',
            'jam_tutup' => '22:00',
            'user_id' => $user->id,
        ]);

        return $user;
    }

    private function createMenuForUser(User $user, array $overrides = []): Menus
    {
        $tenant = Tenants::where('user_id', $user->id)->firstOrFail();

        return Menus::create(array_merge([
            'nama' => 'Menu Test',
            'harga' => 10000,
            'tenant_id' => $tenant->id,
            'isReady' => 1,
        ], $overrides));
    }

    private function createCashierForUser(User $user, Menus $menu, array $overrides = []): Cashier
    {
        $tenant = Tenants::where('user_id', $user->id)->firstOrFail();

        $cashier = Cashier::create(array_merge([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'order_tenant' => 1,
            'kode_pemesanan' => 'ABC12',
            'total' => $menu->harga,
            'status' => 'pending',
        ], $overrides));

        CashierDetail::create([
            'cashier_id' => $cashier->id,
            'menu_id' => $menu->id,
            'jumlah' => 1,
            'harga' => $menu->harga,
        ]);

        return $cashier;
    }

    private function stubMidtransSuccess(): void
    {
        if (! class_exists(\Midtrans\MT_Tests::class)) {
            require_once base_path('vendor/midtrans/midtrans-php/tests/MT_Tests.php');
        }

        \Midtrans\MT_Tests::$stubHttp = true;
        \Midtrans\MT_Tests::$stubHttpResponse = json_encode([
            'actions' => [
                ['url' => 'https://mock.qr/123'],
            ],
            'expiry_time' => now()->addHour()->toDateTimeString(),
        ]);
    }

    private function stubMidtransError(): void
    {
        if (! class_exists(\Midtrans\MT_Tests::class)) {
            require_once base_path('vendor/midtrans/midtrans-php/tests/MT_Tests.php');
        }

        \Midtrans\MT_Tests::$stubHttp = true;
        \Midtrans\MT_Tests::$stubHttpResponse = json_encode([
            'status_code' => '500',
            'status_message' => 'Midtrans service unavailable',
        ]);
    }

    /* ============================================================
     * Store
     * ============================================================ */

    /** @test */
    public function store_returns_validation_error()
    {
        $user = $this->createTenantUser();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/kasir', []);

        $response->assertStatus(400)
            ->assertJsonPath('status', 'failed');
    }

    /** @test */
    public function store_returns_forbidden_for_non_owner()
    {
        $owner = $this->createTenantUser(['email' => 'owner@example.com']);
        $menu = $this->createMenuForUser($owner);

        $intruder = $this->createTenantUser(['email' => 'intruder@example.com']);

        Sanctum::actingAs($intruder);

        $response = $this->postJson('/api/tenant/kasir', [
            'menus' => [
                ['id' => $menu->id, 'jumlah' => 1],
            ],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'Kamu bukan pemilik tenant ini, tidak bisa menambahkan transaksi kasir');
    }

    /** @test */
    public function store_returns_success_response()
    {
        $this->stubMidtransSuccess();

        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/kasir', [
            'menus' => [
                ['id' => $menu->id, 'jumlah' => 2, 'catatan' => 'Extra pedas'],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Transaksi kasir berhasil dibuat')
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function store_returns_server_error()
    {
        $this->stubMidtransError();

        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/kasir', [
            'menus' => [
                ['id' => $menu->id, 'jumlah' => 1],
            ],
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('status', 'failed');
    }

    /* ============================================================
     * Get History
     * ============================================================ */

    /** @test */
    public function get_history_returns_cashier_list()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);
        $this->createCashierForUser($user, $menu);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant/kasir/riwayat');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Berhasil mengambil riwayat kasir')
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function get_history_returns_empty_list()
    {
        $user = $this->createTenantUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant/kasir/riwayat');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Belum ada transaksi kasir untuk tenant ini')
            ->assertJsonCount(0, 'data');
    }

    /* ============================================================
     * Get History By Id
     * ============================================================ */

    /** @test */
    public function get_history_by_id_returns_cashier_detail()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);
        $cashier = $this->createCashierForUser($user, $menu);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant/kasir/riwayat/' . $cashier->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Berhasil mengambil detail transaksi kasir')
            ->assertJsonStructure(['data']);
    }

    /** @test */
    public function get_history_by_id_returns_not_found()
    {
        $user = $this->createTenantUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant/kasir/riwayat/99999');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'failed');
    }

    /* ============================================================
     * Update
     * ============================================================ */

    /** @test */
    public function update_returns_success()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);
        $cashier = $this->createCashierForUser($user, $menu);

        $newMenu = $this->createMenuForUser($user, ['nama' => 'Menu Update', 'harga' => 15000]);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/tenant/kasir/' . $cashier->id, [
            'menus' => [
                ['id' => $newMenu->id, 'jumlah' => 1],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Transaksi kasir berhasil diperbarui');
    }

    /** @test */
    public function update_returns_not_found()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/tenant/kasir/99999', [
            'menus' => [
                ['id' => $menu->id, 'jumlah' => 1],
            ],
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('status', 'failed');
    }

    /** @test */
    public function update_returns_forbidden_for_non_owner()
    {
        $owner = $this->createTenantUser(['email' => 'owner@example.com']);
        $menu = $this->createMenuForUser($owner);
        $cashier = $this->createCashierForUser($owner, $menu);

        $intruder = $this->createTenantUser(['email' => 'intruder@example.com']);

        Sanctum::actingAs($intruder);

        $response = $this->putJson('/api/tenant/kasir/' . $cashier->id, [
            'menus' => [
                ['id' => $menu->id, 'jumlah' => 1],
            ],
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed');
    }

    /* ============================================================
     * Destroy
     * ============================================================ */

    /** @test */
    public function destroy_returns_success()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenuForUser($user);
        $cashier = $this->createCashierForUser($user, $menu);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/tenant/kasir/' . $cashier->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Transaksi kasir berhasil dihapus');
    }

    /** @test */
    public function destroy_returns_not_found()
    {
        $user = $this->createTenantUser();

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/tenant/kasir/99999');

        $response->assertStatus(404)
            ->assertJsonPath('status', 'failed');
    }

    /** @test */
    public function destroy_returns_forbidden_for_non_owner()
    {
        $owner = $this->createTenantUser(['email' => 'owner@example.com']);
        $menu = $this->createMenuForUser($owner);
        $cashier = $this->createCashierForUser($owner, $menu);

        $intruder = $this->createTenantUser(['email' => 'intruder@example.com']);

        Sanctum::actingAs($intruder);

        $response = $this->deleteJson('/api/tenant/kasir/' . $cashier->id);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed');
    }
}
