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

        // 1. Ambil checksum/version status online terkini
        $onlineChecksum = $this->getOnlineStatusChecksum();
        $cacheKey = 'tenants_data_v' . $onlineChecksum;

        // 2. Coba ambil dari cache dengan version key
        $orderedTenants = Cache::get($cacheKey);

        // 3. Jika cache tidak ada (karena status online berubah), query dari database
        if (!$orderedTenants) {
            $tenants = Tenants::with(['listMenu', 'pemilik'])
                ->get()
                ->filter(fn($tenant) => $tenant->pemilik)
                ->values();

            $myTenant = $tenants->where('user_id', $user->id);
            $otherTenants = $tenants->where('user_id', '!=', $user->id);

            $orderedTenants = $myTenant->concat($otherTenants)
                ->sortByDesc('transaksi_berhasil')
                ->values();

            // 4. Simpan ke cache dengan TTL lama (karena cache key berdasarkan status online)
            Cache::put($cacheKey, $orderedTenants, 86400); // 24 jam
        }

        return ResponseApi::success([
            'tenants' => $orderedTenants,
            'cache_info' => [
                'cache_key' => $cacheKey,
                'online_checksum' => $onlineChecksum,
                'cached' => Cache::has($cacheKey)
            ]
        ], 'berhasil mendapatkan data');
    }

    /**
     * Generate checksum berdasarkan status online semua user
     * Checksum akan berubah jika ada perubahan status online
     */
    private function getOnlineStatusChecksum()
    {
        // Ambil semua status online dari user yang memiliki tenant
        $onlineStatuses = DB::table('users')
            ->join('tenants', 'users.id', '=', 'tenants.user_id')
            ->select('users.id', 'users.isOnline', 'users.manual_offline', 'users.manual_override')
            ->orderBy('users.id')
            ->get()
            ->toArray();

        // Buat string untuk checksum
        $statusString = '';
        foreach ($onlineStatuses as $status) {
            $statusString .= "{$status->id}:{$status->isOnline}:{$status->manual_offline}:{$status->manual_override}|";
        }

        // Generate checksum (bisa juga pakai hash dari data)
        $checksum = md5($statusString);

        return $checksum;
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
