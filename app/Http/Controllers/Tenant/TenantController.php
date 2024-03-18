<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Response\ResponseApi;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function getAll(Request $request){
        $user = $request->user()->can('read beranda');

        if(!$user){
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenants = Tenants::with(['listMenu'])->get();

        return ResponseApi::success(compact('tenants'), 'berhasil mendapatkan data');
    }

    public function getSpecificTenant(Request $request, $TenantId){
        $user = $request->user()->can('read beranda');

        if(!$user){
            ResponseApi::error('tidak memiliki akses', 403);
        }

        $tenant = Tenants::with(['listMenu'])->find($TenantId);

        return ResponseApi::success(compact('tenant'), 'berhasil mendapatkan data');
    }
}
