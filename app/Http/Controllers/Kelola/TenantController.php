<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Response\ResponseApi;
use App\Services\Tenant\Actions\CreateMenuAction;
use App\Services\Tenant\Actions\UpdateMenuAction;
use App\Services\Tenant\Actions\DeleteMenuAction;
use App\Services\Tenant\Actions\ShowHistoryAction;
use App\Services\Tenant\Actions\InteruptBusyAction;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    protected ResponseApi $response;

    public function __construct(ResponseApi $response)
    {
        $this->response = $response;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read kelola tenant')) {
            return $this->response->forbidden('tidak memiliki akses');
        }

        return $this->response->success(
            ['tenant' => $user->tenant],
            'berhasil mengambil data'
        );
    }

    public function storeMenu(Request $request, CreateMenuAction $action)
    {
        $this->authorize('create katalog');

        $menu = $action->execute($request);

        return $this->response->success(['menu' => $menu], 'menu berhasil ditambahkan');
    }

    public function updateMenu(Request $request, $id, UpdateMenuAction $action)
    {
        $this->authorize('update katalog');

        $menu = $action->execute($id, $request);

        return $this->response->success(['menu' => $menu], 'menu berhasil diupdate');
    }

    public function updateMenuWeb(Request $request, $id, UpdateMenuAction $action)
    {
        $this->authorize('update katalog');

        $menu = $action->execute($id, $request);

        return response()->json([
            'status'   => 'success',
            'messages' => 'berhasil update menu',
            'data'     => $menu
        ]);
    }

    public function destroyMenu($id, DeleteMenuAction $action)
    {
        $this->authorize('delete katalog');

        $action->execute($id);

        return $this->response->success(null, 'menu dihapus');
    }

    public function showHistoryTransaksiTenant(Request $request, ShowHistoryAction $action)
    {
        $history = $action->execute($request);

        return $this->response->success(['history' => $history], 'berhasil mengambil data');
    }

    public function interuptBusy(Request $request, InteruptBusyAction $action)
    {
        $tenant = $action->execute($request->user());

        return $this->response->success(['tenant' => $tenant], 'Tenant berhasil interupt busy');
    }
}
?>