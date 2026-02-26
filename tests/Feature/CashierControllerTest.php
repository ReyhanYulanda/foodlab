<?php

namespace Tests\Feature;

use App\Http\Controllers\CashierController;
use App\Services\Cashier\Actions\StoreCashierAction;
use App\Services\Cashier\Actions\GetCashierHistoryAction;
use App\Services\Cashier\Actions\GetCashierHistoryByIdAction;
use App\Services\Cashier\Actions\UpdateCashierAction;
use App\Services\Cashier\Actions\DestroyCashierAction;
use Illuminate\Http\Request;
use Tests\TestCase;
use Mockery;

class CashierControllerTest extends TestCase
{
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CashierController();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test store returns success with 201 status
     */
    public function test_store_returns_success_response()
    {
        $mockAction = Mockery::mock(StoreCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => false,
                'status' => 201,
                'message' => 'Transaksi kasir berhasil dibuat',
                'data' => ['id' => 1, 'total' => 25000, 'order_id_midtrans' => 'foodlabs-xxx'],
            ]);

        $request = Request::create('/api/kasir', 'POST');

        $response = $this->controller->store($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Transaksi kasir berhasil dibuat', $data['message']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test store returns validation error with 400 status
     */
    public function test_store_returns_validation_error()
    {
        $mockAction = Mockery::mock(StoreCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 400,
                'message' => ['The menus field is required.'],
            ]);

        $request = Request::create('/api/kasir', 'POST');

        $response = $this->controller->store($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test store returns forbidden when not tenant owner
     */
    public function test_store_returns_forbidden_for_non_owner()
    {
        $mockAction = Mockery::mock(StoreCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menambahkan transaksi kasir',
            ]);

        $request = Request::create('/api/kasir', 'POST');

        $response = $this->controller->store($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test store returns 500 on server error
     */
    public function test_store_returns_server_error()
    {
        $mockAction = Mockery::mock(StoreCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 500,
                'message' => 'Terjadi kesalahan: Something went wrong',
            ]);

        $request = Request::create('/api/kasir', 'POST');

        $response = $this->controller->store($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test getHistory returns cashier history list
     */
    public function test_get_history_returns_cashier_list()
    {
        $mockAction = Mockery::mock(GetCashierHistoryAction::class);
        $mockHistory = [
            ['id' => 1, 'total' => 15000, 'order_tenant' => 1],
            ['id' => 2, 'total' => 20000, 'order_tenant' => 2],
        ];
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'empty' => false,
                'data' => $mockHistory,
                'message' => 'Berhasil mengambil riwayat kasir',
            ]);

        $request = Request::create('/api/kasir/riwayat', 'GET');

        $response = $this->controller->getHistory($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Berhasil mengambil riwayat kasir', $data['message']);
        $this->assertCount(2, $data['data']);
    }

    /**
     * Test getHistory returns empty list
     */
    public function test_get_history_returns_empty_list()
    {
        $mockAction = Mockery::mock(GetCashierHistoryAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'empty' => true,
                'data' => [],
                'message' => 'Belum ada transaksi kasir untuk tenant ini',
            ]);

        $request = Request::create('/api/kasir/riwayat', 'GET');

        $response = $this->controller->getHistory($request, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEmpty($data['data']);
    }

    /**
     * Test getHistoryById returns single cashier detail
     */
    public function test_get_history_by_id_returns_cashier_detail()
    {
        $mockAction = Mockery::mock(GetCashierHistoryByIdAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1, Mockery::type(Request::class))
            ->andReturn([
                'error' => false,
                'data' => ['id' => 1, 'total' => 25000, 'order_id_midtrans' => 'foodlabs-xxx'],
                'message' => 'Berhasil mengambil detail transaksi kasir',
            ]);

        $request = Request::create('/api/kasir/riwayat/1', 'GET');

        $response = $this->controller->getHistoryById($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test getHistoryById returns 404 when not found
     */
    public function test_get_history_by_id_returns_not_found()
    {
        $mockAction = Mockery::mock(GetCashierHistoryByIdAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan atau tidak memiliki akses',
            ]);

        $request = Request::create('/api/kasir/riwayat/999', 'GET');

        $response = $this->controller->getHistoryById($request, 999, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test update returns success
     */
    public function test_update_returns_success()
    {
        $mockAction = Mockery::mock(UpdateCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1, Mockery::type(Request::class))
            ->andReturn([
                'error' => false,
                'status' => 200,
                'message' => 'Transaksi kasir berhasil diperbarui',
                'data' => ['id' => 1, 'total' => 30000],
            ]);

        $request = Request::create('/api/kasir/1', 'PUT');

        $response = $this->controller->update($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Transaksi kasir berhasil diperbarui', $data['message']);
    }

    /**
     * Test update returns 404 when cashier not found
     */
    public function test_update_returns_not_found()
    {
        $mockAction = Mockery::mock(UpdateCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan',
            ]);

        $request = Request::create('/api/kasir/999', 'PUT');

        $response = $this->controller->update($request, 999, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test update returns 403 when not owner
     */
    public function test_update_returns_forbidden_for_non_owner()
    {
        $mockAction = Mockery::mock(UpdateCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa mengubah transaksi kasir',
            ]);

        $request = Request::create('/api/kasir/1', 'PUT');

        $response = $this->controller->update($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test destroy returns success
     */
    public function test_destroy_returns_success()
    {
        $mockAction = Mockery::mock(DestroyCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1, Mockery::type(Request::class))
            ->andReturn([
                'error' => false,
                'status' => 200,
                'message' => 'Transaksi kasir berhasil dihapus',
            ]);

        $request = Request::create('/api/kasir/1', 'DELETE');

        $response = $this->controller->destroy($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Transaksi kasir berhasil dihapus', $data['message']);
    }

    /**
     * Test destroy returns 404 when not found
     */
    public function test_destroy_returns_not_found()
    {
        $mockAction = Mockery::mock(DestroyCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 404,
                'message' => 'Transaksi kasir tidak ditemukan',
            ]);

        $request = Request::create('/api/kasir/999', 'DELETE');

        $response = $this->controller->destroy($request, 999, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }

    /**
     * Test destroy returns 403 when not owner
     */
    public function test_destroy_returns_forbidden_for_non_owner()
    {
        $mockAction = Mockery::mock(DestroyCashierAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn([
                'error' => true,
                'status' => 403,
                'message' => 'Kamu bukan pemilik tenant ini, tidak bisa menghapus transaksi kasir',
            ]);

        $request = Request::create('/api/kasir/1', 'DELETE');

        $response = $this->controller->destroy($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('failed', $data['status']);
    }
}
