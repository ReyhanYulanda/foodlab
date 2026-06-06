<?php

namespace Tests\Feature\Api\SaldoKoin;

use App\Models\SaldoKoin;
use App\Models\TransaksiSaldoKoin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middlewares\RoleMiddleware;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SaldoKoinControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Align guard so Spatie permission checks work with Sanctum
        Config::set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        Config::set('auth.defaults.guard', 'sanctum');

        // Bypass role middleware so we can focus on gate/permission checks
        $this->withoutMiddleware(RoleMiddleware::class);
    }

    /* ============================================================
     * Helpers
     * ============================================================ */

    private function givePermission(User $user, string $name): void
    {
        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'sanctum',
        ]);
        $user->givePermissionTo($permission);
    }

    /* ============================================================
     * Cek Saldo
     * ============================================================ */

    /** @test */
    public function cek_saldo_returns_saldo_successfully()
    {
        $user = User::factory()->create();
        SaldoKoin::create(['user_id' => $user->id, 'jumlah' => 50000]);
        $this->givePermission($user, 'read saldo_koin');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/saldo');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('saldo_koin', 50000);
    }

    /** @test */
    public function cek_saldo_requires_authorization()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/saldo');

        $response->assertStatus(403);
    }

    /* ============================================================
     * Riwayat Transaksi
     * ============================================================ */

    /** @test */
    public function riwayat_transaksi_returns_paginated_data()
    {
        $user = User::factory()->create();
        $this->givePermission($user, 'read saldo_koin');

        TransaksiSaldoKoin::create([
            'user_id' => $user->id,
            'jumlah' => 10000,
            'tipe' => 'masuk',
            'deskripsi' => 'Top-up',
        ]);
        TransaksiSaldoKoin::create([
            'user_id' => $user->id,
            'jumlah' => -5000,
            'tipe' => 'keluar',
            'deskripsi' => 'Pembayaran',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/saldo/riwayat');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'data berhasil didapatkan')
            ->assertJsonStructure(['transaksi' => ['data', 'current_page', 'total']]);
    }

    /** @test */
    public function riwayat_transaksi_requires_authorization()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/saldo/riwayat');

        $response->assertStatus(403);
    }

    /* ============================================================
     * Transfer Coin
     * ============================================================ */

    /** @test */
    public function transfer_coin_returns_success()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        SaldoKoin::create(['user_id' => $sender->id, 'jumlah' => 50000]);
        SaldoKoin::create(['user_id' => $receiver->id, 'jumlah' => 0]);

        $this->givePermission($sender, 'read transfer_coin');

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/admin/coin/tf/backdoor', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'jumlah' => 10000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Transfer sebesar Rp 10.000 berhasil')
            ->assertJsonStructure(['data' => ['sender', 'receiver']]);
    }

    /** @test */
    public function transfer_coin_returns_insufficient_balance()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        // Sender has no saldo record (or 0 balance)
        SaldoKoin::create(['user_id' => $sender->id, 'jumlah' => 0]);
        SaldoKoin::create(['user_id' => $receiver->id, 'jumlah' => 0]);

        $this->givePermission($sender, 'read transfer_coin');

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/admin/coin/tf/backdoor', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'jumlah' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Saldo pengirim tidak mencukupi');
    }

    /** @test */
    public function transfer_coin_requires_authorization()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();

        Sanctum::actingAs($sender);

        $response = $this->postJson('/api/admin/coin/tf/backdoor', [
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'jumlah' => 10000,
        ]);

        $response->assertStatus(403);
    }
}
