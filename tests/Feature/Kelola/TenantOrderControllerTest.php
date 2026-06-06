<?php

namespace Tests\Feature\Kelola;

use App\Http\Controllers\Kelola\TenantOrderController;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use App\Services\Kelola\TenantOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Mockery;

class TenantOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(TenantOrderService::class);
        $this->controller = new TenantOrderController($this->mockService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ===============================
    // Tests for index()
    // ===============================

    /**
     * Test index returns order data with permission
     */
    public function test_index_returns_order_data_with_permission()
    {
        $user = User::factory()->create();

        // Mock permission
        Gate::define('read order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        // Mock service
        $mockData = collect([
            ['id' => 1, 'status' => 'pending'],
            ['id' => 2, 'status' => 'selesai'],
        ]);
        $this->mockService->shouldReceive('getDataPesanan')
            ->once()
            ->with($user->id, null)
            ->andReturn($mockData);

        // Create request
        $request = Request::create('/api/order', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Execute
        $response = $this->controller->index($request);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Berhasil mengambil data', $data['message']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test index returns order data filtered by status
     */
    public function test_index_returns_order_data_filtered_by_status()
    {
        $user = User::factory()->create();

        Gate::define('read order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockData = collect([
            ['id' => 1, 'status' => 'pending'],
        ]);
        $this->mockService->shouldReceive('getDataPesanan')
            ->once()
            ->with($user->id, 'pending')
            ->andReturn($mockData);

        $request = Request::create('/api/order', 'GET', ['status' => 'pending']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->index($request);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
    }

    /**
     * Test index returns forbidden without permission
     */
    public function test_index_returns_forbidden_without_permission()
    {
        $user = User::factory()->create();

        Gate::define('read order tenant', function () {
            return false;
        });

        $request = Request::create('/api/order', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->index($request);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(403, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('tidak memiliki akses', $data['message']);
    }

    /**
     * Test index returns server error when service throws exception
     */
    public function test_index_returns_server_error_on_exception()
    {
        $user = User::factory()->create();

        Gate::define('read order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $this->mockService->shouldReceive('getDataPesanan')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $request = Request::create('/api/order', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->index($request);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(500, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Kesalahan Pada Server', $data['message']);
    }

    // ===============================
    // Tests for update()
    // ===============================

    /**
     * Test update calls service with permission
     */
    public function test_update_calls_service_with_permission()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockFirebases = Mockery::mock(Firebases::class);
        $expectedResponse = ResponseApi::success(null, 'Pesanan selesai');

        $this->mockService->shouldReceive('updateStatusPesanan')
            ->once()
            ->with(Mockery::type(Request::class), $mockFirebases, 1)
            ->andReturn($expectedResponse);

        $request = Request::create('/api/order/1', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->update($request, $mockFirebases, 1);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
    }

    /**
     * Test update returns forbidden without permission
     */
    public function test_update_returns_forbidden_without_permission()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function () {
            return false;
        });

        $mockFirebases = Mockery::mock(Firebases::class);

        $request = Request::create('/api/order/1', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->update($request, $mockFirebases, 1);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(403, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('tidak memiliki akses', $data['message']);
    }

    /**
     * Test update returns error when order not found (service returns 404)
     */
    public function test_update_returns_error_when_order_not_found()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockFirebases = Mockery::mock(Firebases::class);
        $errorResponse = ResponseApi::error('pesanan tidak ditemukan', 404);

        $this->mockService->shouldReceive('updateStatusPesanan')
            ->once()
            ->andReturn($errorResponse);

        $request = Request::create('/api/order/999', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->update($request, $mockFirebases, 999);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(404, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('pesanan tidak ditemukan', $data['message']);
    }

    // ===============================
    // Tests for updateCashier()
    // ===============================

    /**
     * Test updateCashier calls service with permission
     */
    public function test_update_cashier_calls_service_with_permission()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockFirebases = Mockery::mock(Firebases::class);
        $expectedResponse = ResponseApi::success(null, 'Status pesanan kasir berhasil diperbarui menjadi selesai');

        $this->mockService->shouldReceive('updateStatusPesananCashier')
            ->once()
            ->with(Mockery::type(Request::class), $mockFirebases, 1)
            ->andReturn($expectedResponse);

        $request = Request::create('/api/kasir/order/1', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->updateCashier($request, $mockFirebases, 1);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
    }

    /**
     * Test updateCashier returns forbidden without permission
     */
    public function test_update_cashier_returns_forbidden_without_permission()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function () {
            return false;
        });

        $mockFirebases = Mockery::mock(Firebases::class);

        $request = Request::create('/api/kasir/order/1', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->updateCashier($request, $mockFirebases, 1);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(403, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('tidak memiliki akses', $data['message']);
    }

    /**
     * Test updateCashier returns error when cashier order not found (service returns 404)
     */
    public function test_update_cashier_returns_error_when_not_found()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockFirebases = Mockery::mock(Firebases::class);
        $errorResponse = ResponseApi::error('Transaksi kasir tidak ditemukan', 404);

        $this->mockService->shouldReceive('updateStatusPesananCashier')
            ->once()
            ->andReturn($errorResponse);

        $request = Request::create('/api/kasir/order/999', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->updateCashier($request, $mockFirebases, 999);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(404, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Transaksi kasir tidak ditemukan', $data['message']);
    }

    /**
     * Test updateCashier returns error for invalid status transition (service returns 400)
     */
    public function test_update_cashier_returns_error_for_invalid_status_transition()
    {
        $user = User::factory()->create();

        Gate::define('update order tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        $mockFirebases = Mockery::mock(Firebases::class);
        $errorResponse = ResponseApi::error('Pesanan kasir sudah selesai sebelumnya.', 400);

        $this->mockService->shouldReceive('updateStatusPesananCashier')
            ->once()
            ->andReturn($errorResponse);

        $request = Request::create('/api/kasir/order/1', 'PUT', ['status' => 'selesai']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        $response = $this->controller->updateCashier($request, $mockFirebases, 1);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        $this->assertEquals(400, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('Pesanan kasir sudah selesai sebelumnya.', $data['message']);
    }
}
