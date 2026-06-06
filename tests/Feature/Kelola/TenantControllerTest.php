<?php

namespace Tests\Feature\Kelola;

use App\Http\Controllers\Kelola\TenantController;
use App\Models\User;
use App\Models\Tenants;
use App\Response\ResponseApi;
use App\Services\Tenant\Actions\CreateMenuAction;
use App\Services\Tenant\Actions\UpdateMenuAction;
use App\Services\Tenant\Actions\DeleteMenuAction;
use App\Services\Tenant\Actions\ShowHistoryAction;
use App\Services\Tenant\Actions\InteruptBusyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Mockery;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $responseApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responseApi = new ResponseApi();
        $this->controller = new TenantController($this->responseApi);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test index method returns tenant data with permission
     */
    public function test_index_returns_tenant_data_with_permission()
    {
        // Create a user with tenant
        $user = User::factory()->create();
        $tenant = Tenants::factory()->create(['user_id' => $user->id]);
        $user->load('tenant');

        // Mock permission check
        Gate::define('read kelola tenant', function ($authUser) use ($user) {
            return $authUser->id === $user->id;
        });

        // Create request with authenticated user
        $request = Request::create('/api/tenant', 'GET');
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
        $this->assertEquals('berhasil mengambil data', $data['message']);
        $this->assertArrayHasKey('tenant', $data['data']);
    }

    /**
     * Test index method returns forbidden without permission
     */
    public function test_index_returns_forbidden_without_permission()
    {
        // Create a user
        $user = User::factory()->create();

        // Mock permission check to return false
        Gate::define('read kelola tenant', function () {
            return false;
        });

        // Create request with authenticated user
        $request = Request::create('/api/tenant', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Execute
        $response = $this->controller->index($request);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(403, $jsonResponse->getStatusCode());
        $this->assertEquals('failed', $data['status']);
        $this->assertEquals('tidak memiliki akses', $data['message']);
    }

    /**
     * Test storeMenu creates menu successfully
     */
    public function test_store_menu_creates_menu_successfully()
    {
        // Create user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        Gate::define('create katalog', function () {
            return true;
        });

        // Mock CreateMenuAction
        $mockAction = Mockery::mock(CreateMenuAction::class);
        $mockMenu = ['id' => 1, 'name' => 'Test Menu', 'price' => 10000];
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn($mockMenu);

        // Create request
        $request = Request::create('/api/menu', 'POST', [
            'name' => 'Test Menu',
            'price' => 10000
        ]);

        // Execute
        $response = $this->controller->storeMenu($request, $mockAction);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('menu berhasil ditambahkan', $data['message']);
        $this->assertArrayHasKey('menu', $data['data']);
        $this->assertEquals($mockMenu, $data['data']['menu']);
    }

    /**
     * Test storeMenu requires authorization
     */
    public function test_store_menu_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        // Create user without permission
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization to fail
        Gate::define('create katalog', function () {
            return false;
        });

        // Mock CreateMenuAction
        $mockAction = Mockery::mock(CreateMenuAction::class);

        // Create request
        $request = Request::create('/api/menu', 'POST');

        // Execute - should throw AuthorizationException
        $this->controller->storeMenu($request, $mockAction);
    }

    /**
     * Test updateMenu updates menu successfully
     */
    public function test_update_menu_updates_menu_successfully()
    {
        // Create user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        Gate::define('update katalog', function () {
            return true;
        });

        // Mock UpdateMenuAction
        $mockAction = Mockery::mock(UpdateMenuAction::class);
        $mockMenu = ['id' => 1, 'name' => 'Updated Menu', 'price' => 15000];
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1, Mockery::type(Request::class))
            ->andReturn($mockMenu);

        // Create request
        $request = Request::create('/api/menu/1', 'PUT', [
            'name' => 'Updated Menu',
            'price' => 15000
        ]);

        // Execute
        $response = $this->controller->updateMenu($request, 1, $mockAction);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('menu berhasil diupdate', $data['message']);
        $this->assertArrayHasKey('menu', $data['data']);
        $this->assertEquals($mockMenu, $data['data']['menu']);
    }

    /**
     * Test updateMenu requires authorization
     */
    public function test_update_menu_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        // Create user without permission
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization to fail
        Gate::define('update katalog', function () {
            return false;
        });

        // Mock UpdateMenuAction
        $mockAction = Mockery::mock(UpdateMenuAction::class);

        // Create request
        $request = Request::create('/api/menu/1', 'PUT');

        // Execute - should throw AuthorizationException
        $this->controller->updateMenu($request, 1, $mockAction);
    }

    /**
     * Test updateMenuWeb returns JSON response
     */
    public function test_update_menu_web_returns_json_response()
    {
        // Create user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        Gate::define('update katalog', function () {
            return true;
        });

        // Mock UpdateMenuAction
        $mockAction = Mockery::mock(UpdateMenuAction::class);
        $mockMenu = ['id' => 1, 'name' => 'Updated Menu Web', 'price' => 20000];
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1, Mockery::type(Request::class))
            ->andReturn($mockMenu);

        // Create request
        $request = Request::create('/web/menu/1', 'PUT', [
            'name' => 'Updated Menu Web',
            'price' => 20000
        ]);

        // Execute
        $response = $this->controller->updateMenuWeb($request, 1, $mockAction);
        $data = json_decode($response->getContent(), true);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('berhasil update menu', $data['messages']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals($mockMenu, $data['data']);
    }

    /**
     * Test destroyMenu deletes menu successfully
     */
    public function test_destroy_menu_deletes_menu_successfully()
    {
        // Create user
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization
        Gate::define('delete katalog', function () {
            return true;
        });

        // Mock DeleteMenuAction
        $mockAction = Mockery::mock(DeleteMenuAction::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(1)
            ->andReturn(true);

        // Create request for response
        $request = Request::create('/api/menu/1', 'DELETE');

        // Execute
        $response = $this->controller->destroyMenu(1, $mockAction);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('menu dihapus', $data['message']);
    }

    /**
     * Test destroyMenu requires authorization
     */
    public function test_destroy_menu_requires_authorization()
    {
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        // Create user without permission
        $user = User::factory()->create();
        $this->actingAs($user);

        // Mock authorization to fail
        Gate::define('delete katalog', function () {
            return false;
        });

        // Mock DeleteMenuAction
        $mockAction = Mockery::mock(DeleteMenuAction::class);

        // Execute - should throw AuthorizationException
        $this->controller->destroyMenu(1, $mockAction);
    }

    /**
     * Test showHistoryTransaksiTenant returns history
     */
    public function test_show_history_transaksi_tenant_returns_history()
    {
        // Create user
        $user = User::factory()->create();

        // Mock ShowHistoryAction
        $mockAction = Mockery::mock(ShowHistoryAction::class);
        $mockHistory = [
            ['id' => 1, 'transaction_id' => 'TRX001', 'status' => 'completed'],
            ['id' => 2, 'transaction_id' => 'TRX002', 'status' => 'pending']
        ];
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(Request::class))
            ->andReturn($mockHistory);

        // Create request
        $request = Request::create('/api/history', 'GET');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Execute
        $response = $this->controller->showHistoryTransaksiTenant($request, $mockAction);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('berhasil mengambil data', $data['message']);
        $this->assertArrayHasKey('history', $data['data']);
        $this->assertEquals($mockHistory, $data['data']['history']);
    }

    /**
     * Test interuptBusy updates tenant status
     */
    public function test_interupt_busy_updates_tenant_status()
    {
        // Create user with tenant
        $user = User::factory()->create();
        $tenant = Tenants::factory()->create([
            'user_id' => $user->id,
            'is_busy' => \Carbon\Carbon::now()
        ]);
        $user->load('tenant');

        // Mock InteruptBusyAction
        $mockAction = Mockery::mock(InteruptBusyAction::class);
        $updatedTenant = ['id' => $tenant->id, 'is_busy' => false];
        $mockAction->shouldReceive('execute')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn($updatedTenant);

        // Create request
        $request = Request::create('/api/tenant/interupt-busy', 'POST');
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Execute
        $response = $this->controller->interuptBusy($request, $mockAction);
        $jsonResponse = $response->toResponse($request);
        $data = json_decode($jsonResponse->getContent(), true);

        // Assert
        $this->assertEquals(200, $jsonResponse->getStatusCode());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Tenant berhasil interupt busy', $data['message']);
        $this->assertArrayHasKey('tenant', $data['data']);
        $this->assertEquals($updatedTenant, $data['data']['tenant']);
    }
}
