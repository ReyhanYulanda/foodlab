<?php

namespace Tests\Feature\Kelola;

use App\Models\Kategori;
use App\Models\Menus;
use App\Models\Pengaturan;
use App\Models\Tenants;
use App\Models\Transaksi;
use App\Models\TransaksiDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middlewares\RoleMiddleware;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Spatie Permission can validate the sanctum guard
        Config::set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        Config::set('auth.defaults.guard', 'sanctum');

        // Bypass Spatie role middleware
        $this->withoutMiddleware(RoleMiddleware::class);

        // Fake public disk so file uploads don't touch real storage
        Storage::fake('public');
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

    private function createKategori(): int
    {
        return DB::table('kategori')->insertGetId([
            'nama' => 'Kategori Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMenu(User $user, array $overrides = []): Menus
    {
        $tenant = Tenants::where('user_id', $user->id)->firstOrFail();
        $kategoriId = $this->createKategori();

        return Menus::create(array_merge([
            'nama' => 'Menu Test',
            'harga' => 10000,
            'tenant_id' => $tenant->id,
            'kategori_id' => $kategoriId,
            'isReady' => 1,
        ], $overrides));
    }

    private function givePermission(User $user, string $name): void
    {
        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'sanctum',
        ]);
        $user->givePermissionTo($permission);
    }

    /* ============================================================
     * Index
     * ============================================================ */

    /** @test */
    public function index_returns_tenant_data_with_permission()
    {
        $user = $this->createTenantUser();
        $this->givePermission($user, 'read kelola tenant');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'berhasil mengambil data')
            ->assertJsonStructure(['data' => ['tenant']]);
    }

    /** @test */
    public function index_returns_forbidden_without_permission()
    {
        $user = $this->createTenantUser();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant');

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'tidak memiliki akses');
    }

    /* ============================================================
     * Store Menu
     * ============================================================ */

    /** @test */
    public function store_menu_creates_menu_successfully()
    {
        $user = $this->createTenantUser();
        $this->givePermission($user, 'create katalog');
        $kategoriId = $this->createKategori();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/menu', [
            'nama_menu' => 'Nasi Goreng',
            'harga' => 15000,
            'kategori_id' => $kategoriId,
            'deskripsi_menu' => 'Deskripsi singkat',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'menu makanan berhasil ditambahkan')
            ->assertJsonStructure(['data' => ['newMenu']]);
    }

    /** @test */
    public function store_menu_requires_authorization()
    {
        $user = $this->createTenantUser();
        $kategoriId = $this->createKategori();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/menu', [
            'nama_menu' => 'Nasi Goreng',
            'harga' => 15000,
            'kategori_id' => $kategoriId,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'tidak memiliki akses');
    }

    /* ============================================================
     * Update Menu
     * ============================================================ */

    /** @test */
    public function update_menu_updates_menu_successfully()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);
        $this->givePermission($user, 'update katalog');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/menu/' . $menu->id, [
            'nama_menu' => 'Nasi Goreng Spesial',
            'harga' => 18000,
            'isReady' => 0,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'menu makanan berhasil diupdate');

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'nama' => 'Nasi Goreng Spesial',
            'harga' => 18000,
            'isReady' => 0,
        ]);
    }

    /** @test */
    public function update_menu_requires_authorization()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/menu/' . $menu->id, [
            'nama_menu' => 'Nasi Goreng Spesial',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'tidak memiliki akses');
    }

    /* ============================================================
     * Update Menu Web
     * ============================================================ */

    /** @test */
    public function update_menu_web_returns_json_response()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);

        Sanctum::actingAs($user);

        // Route is POST /api/menu/{id} (outside tenant prefix and role middleware)
        $response = $this->postJson('/api/menu/' . $menu->id, [
            'nama_menu' => 'Updated Menu Web',
            'harga' => 20000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('messages', 'berhasil update menu');

        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'nama' => 'Updated Menu Web',
            'harga' => 20000,
        ]);
    }

    /* ============================================================
     * Destroy Menu
     * ============================================================ */

    /** @test */
    public function destroy_menu_deletes_menu_successfully()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);
        $this->givePermission($user, 'delete katalog');

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/tenant/menu/' . $menu->id);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Menu makanan berhasil dihapus');

        $this->assertSoftDeleted('menus', ['id' => $menu->id]);
    }

    /** @test */
    public function destroy_menu_requires_authorization()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/tenant/menu/' . $menu->id);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', 'tidak memiliki akses');
    }

    /* ============================================================
     * Show History Transaksi Tenant
     * ============================================================ */

    /** @test */
    public function show_history_transaksi_tenant_returns_history()
    {
        $user = $this->createTenantUser();
        $menu = $this->createMenu($user);

        $transaksi = Transaksi::create([
            'user_id' => $user->id,
            'ruangan_id' => null,
            'total' => 20000,
            'isAntar' => 1,
            'metode_pembayaran' => 'transfer',
            'status' => 'selesai',
        ]);

        TransaksiDetail::create([
            'transaksi_id' => $transaksi->id,
            'menu_id' => $menu->id,
            'jumlah' => 2,
            'harga' => 10000,
            'status' => 'selesai',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/tenant/history-transaksi-tenant');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data']);
    }

    /* ============================================================
     * Interupt Busy
     * ============================================================ */

    /** @test */
    public function interupt_busy_updates_tenant_status()
    {
        $user = $this->createTenantUser();
        $tenant = Tenants::where('user_id', $user->id)->first();
        $tenant->update(['busy_until' => now()->addMinutes(10)]);

        Pengaturan::create(['nama' => 'busy_until', 'nilai' => 3]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/tenant/interupt');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Tenant berhasil interupt busy')
            ->assertJsonStructure(['tenant']);
    }
}
