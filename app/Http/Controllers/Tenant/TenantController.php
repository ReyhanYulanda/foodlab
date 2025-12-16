<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Menus;
use App\Models\Tenants;
use App\Models\TransaksiDetail;
use App\Response\ResponseApi;
use Illuminate\Http\Request;
use App\Services\Firebases;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

        // Cache key untuk data static (jarang berubah)
        $staticCacheKey = 'tenants_static_data_v2';
        $staticCacheDuration = 3600; // 1 jam

        // Ambil data static dari cache atau database
        $tenants = Cache::remember($staticCacheKey, $staticCacheDuration, function () {
            // Hanya ambil kolom yang benar-benar static
            return Tenants::with([
                'listMenu:id,tenant_id,nama,harga,isReady',
                'pemilik:id,name,email,phone,image' // data user yang static
            ])
                ->select([
                    'id',
                    'nama_tenant',
                    'nama_kavling',
                    'nama_gambar',
                    'user_id',
                    'no_rekening_toko',
                    'no_rekening_pribadi',
                    'is_busy',
                    'busy_until',
                    'is_interupt'
                    // jam_buka, jam_tutup TIDAK di-cache karena dinamis
                ])
                ->get()
                ->filter(fn($tenant) => $tenant->pemilik)
                ->values();
        });

        // Ambil data dinamis real-time dari database
        $dynamicData = DB::table('tenants')
            ->select('id', 'user_id', 'jam_buka', 'jam_tutup')
            ->whereIn('user_id', $tenants->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $onlineStatuses = DB::table('users')
            ->select('id', 'isOnline', 'manual_offline', 'manual_override')
            ->whereIn('id', $tenants->pluck('user_id'))
            ->get()
            ->keyBy('id');

        // Gabungkan data static dengan data dinamis
        $tenants->each(function ($tenant) use ($dynamicData, $onlineStatuses) {
            // Update data dinamis dari tenants
            $tenantDynamic = $dynamicData[$tenant->user_id] ?? null;
            if ($tenantDynamic) {
                $tenant->jam_buka = $tenantDynamic->jam_buka;
                $tenant->jam_tutup = $tenantDynamic->jam_tutup;
            }

            // Update status online dari users
            $userStatus = $onlineStatuses[$tenant->user_id] ?? null;
            if ($userStatus) {
                // Logika isOnline sesuai dengan model User
                $isOnline = $userStatus->isOnline;
                $manualOffline = $userStatus->manual_offline;
                $manualOverride = $userStatus->manual_override;

                // Sesuaikan dengan logika isOnline yang ada di aplikasi
                // (ini contoh, sesuaikan dengan business logic Anda)
                $tenant->pemilik->isOnline = $manualOverride ? false : ($manualOffline ? false : (bool)$isOnline);
            }
        });

        // Urutkan seperti sebelumnya
        $myTenant = $tenants->where('user_id', $user->id);
        $otherTenants = $tenants->where('user_id', '!=', $user->id);

        $orderedTenants = $myTenant->concat($otherTenants)
            ->sortByDesc('transaksi_berhasil')
            ->values();

        return ResponseApi::success([
            'tenants' => $orderedTenants,
            'cache_info' => [
                'static_cached' => true,
                'dynamic_fresh' => true,
                'timestamp' => now()->toISOString()
            ]
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
