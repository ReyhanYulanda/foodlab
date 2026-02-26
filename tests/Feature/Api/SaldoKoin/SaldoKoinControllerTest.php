<?php

namespace Tests\Feature\Api\SaldoKoin;

use App\Http\Controllers\Api\SaldoKoin\SaldoKoinController;
use App\Models\User;
use App\Services\SaldoKoin\Actions\CekSaldoAction;
use App\Services\SaldoKoin\Actions\RiwayatTransaksiAction;
use App\Services\SaldoKoin\Actions\TransferCoinAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Mockery;

class SaldoKoinControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SaldoKoinController();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── cekSaldo ───────────────────────────────────────

    /**
     * Test cekSaldo returns saldo successfully
     */
    public function test_cek_saldo_returns_saldo_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read saldo_koin', fn() => true);

        $mockAction = Mockery::mock(CekSaldoAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::any())
            ->andReturn(['saldo_koin' => 50000]);

        $response = $this->controller->cekSaldo($mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals(50000, $data['saldo_koin']);
    }

    /**
     * Test cekSaldo requires authorization
     */
    public function test_cek_saldo_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read saldo_koin', fn() => false);

        $mockAction = Mockery::mock(CekSaldoAction::class);
        $this->controller->cekSaldo($mockAction);
    }

    // ─── riwayatTransaksi ───────────────────────────────

    /**
     * Test riwayatTransaksi returns paginated data
     */
    public function test_riwayat_transaksi_returns_paginated_data()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read saldo_koin', fn() => true);

        $mockPaginator = new LengthAwarePaginator(
            [
                ['id' => 1, 'jumlah' => 10000, 'tipe' => 'masuk'],
                ['id' => 2, 'jumlah' => -5000, 'tipe' => 'keluar'],
            ],
            2,
            10,
            1
        );

        $mockAction = Mockery::mock(RiwayatTransaksiAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::any(), 10, 1)
            ->andReturn($mockPaginator);

        $request = Request::create('/api/saldo/riwayat', 'GET', [
            'per_page' => 10,
            'page' => 1,
        ]);

        $response = $this->controller->riwayatTransaksi($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('data berhasil didapatkan', $data['message']);
        $this->assertArrayHasKey('transaksi', $data);
    }

    /**
     * Test riwayatTransaksi requires authorization
     */
    public function test_riwayat_transaksi_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read saldo_koin', fn() => false);

        $mockAction = Mockery::mock(RiwayatTransaksiAction::class);
        $request = Request::create('/api/saldo/riwayat', 'GET');

        $this->controller->riwayatTransaksi($request, $mockAction);
    }

    // ─── transferCoin ───────────────────────────────────

    /**
     * Test transferCoin returns success
     */
    public function test_transfer_coin_returns_success()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read transfer_coin', fn() => true);

        $mockAction = Mockery::mock(TransferCoinAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => false,
                'message' => 'Transfer sebesar Rp 10.000 berhasil',
                'data' => [
                    'sender' => ['user_id' => 1, 'jumlah' => 40000],
                    'receiver' => ['user_id' => 2, 'jumlah' => 10000],
                ]
            ]);

        $request = Request::create('/api/coin/tf/backdoor', 'POST', [
            'sender_id' => 1,
            'receiver_id' => 2,
            'jumlah' => 10000,
        ]);

        $response = $this->controller->transferCoin($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('berhasil', $data['message']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('sender', $data['data']);
        $this->assertArrayHasKey('receiver', $data['data']);
    }

    /**
     * Test transferCoin returns insufficient balance
     */
    public function test_transfer_coin_returns_insufficient_balance()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read transfer_coin', fn() => true);

        $mockAction = Mockery::mock(TransferCoinAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 422,
                'message' => 'Saldo pengirim tidak mencukupi'
            ]);

        $request = Request::create('/api/coin/tf/backdoor', 'POST');

        $response = $this->controller->transferCoin($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Saldo pengirim tidak mencukupi', $data['message']);
    }

    /**
     * Test transferCoin requires authorization
     */
    public function test_transfer_coin_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('read transfer_coin', fn() => false);

        $mockAction = Mockery::mock(TransferCoinAction::class);
        $request = Request::create('/api/coin/tf/backdoor', 'POST');

        $this->controller->transferCoin($request, $mockAction);
    }
}
