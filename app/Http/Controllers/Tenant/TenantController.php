<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Menus;
use App\Models\Tenants;
use App\Models\TransaksiDetail;
use App\Response\ResponseApi;
use Illuminate\Http\Request;
use App\Services\Firebases;

class TenantController extends Controller
{
    public function getAll(Request $request, Firebases $firebases)
    {
        $user = $request->user();

        if (!$user->can('read beranda')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenants = Tenants::with(['listMenu', 'pemilik'])
            ->get()
            ->filter(fn($tenant) => $tenant->pemilik)
            ->values();

        $myTenant = $tenants->where('user_id', $user->id);

        $otherTenants = $tenants->where('user_id', '!=', $user->id);

        $orderedTenants = $myTenant->concat($otherTenants)
            ->sortByDesc('transaksi_berhasil')
            ->values();

        return ResponseApi::success([
            'tenants' => $orderedTenants
        ], 'berhasil mendapatkan data');
    }

    public function getMenusById($id)
    {
        $menu = Menus::with(['tenant', 'kategori'])->find($id);

        if (!$menu) {
            return ResponseApi::error('Menu tidak ditemukan', 404);
        }

        return ResponseApi::success(compact('menu'), 'berhasil mendapatkan data menu');
    }


    public function getSpecificTenant(Request $request, $TenantId)
    {
        $user = $request->user()->can('read beranda');

        if (!$user) {
            ResponseApi::error('tidak memiliki akses', 403);
        }

        $tenant = Tenants::with(['listMenu', 'pemilik'])->find($TenantId);

        if (!$tenant || !$tenant->pemilik) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tenant tidak ditemukan atau tidak memiliki pemilik.',
            ], 404);
        }

        return ResponseApi::success(compact('tenant'), 'berhasil mendapatkan data');
    }
}
