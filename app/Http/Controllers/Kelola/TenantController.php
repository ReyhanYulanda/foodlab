<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\Menus;
use App\Models\Tenants;
use App\Models\TransaksiDetail;
use App\Response\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Helper\ValidationHelper;
use App\Services\Kelola\TenantService;

class TenantController extends Controller
{
    protected $tenantService;

    public function __construct(TenantService $tenantService) 
    {
        $this->tenantService = $tenantService;
    }

    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read kelola tenant')) {
            return ResponseApi::forbidden('tidak memiliki akses');
        }

        $tenant = $this->tenantService->getTenantData($user);
        return ResponseApi::success(compact('tenant'), 'berhasil mengambil data');
    }

    public function storeMenu(Request $request)
    {
        $user = $request->user();

        if (!$user->can('create katalog')) {
            return ResponseApi::forbidden('tidak memiliki akses',   403);
        }

        $validation = ValidationHelper::validate($request->all(), [
            'harga' => 'required|numeric',
            'nama_menu' => 'required',
            'deskripsi_menu' => 'nullable',
            'kategori_id' => 'required',
            'gambar' => 'nullable|mimes:png,jpg|max:2048',
        ]);
    
        if ($validation) {
            return $validation;
        }

        try {
            $newMenu = $this->tenantService->storeMenu($request, $user);
            return ResponseApi::success(compact('newMenu'), 'menu makanan berhasil ditambahkan');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return ResponseApi::serverError();
        }
    }

    public function updateMenu(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->can('update katalog')) {
            return ResponseApi::forbidden('tidak memiliki akses',   403);
        }

        $tenant = Tenants::where("user_id", $request->user()->id)->first();
        $menu = Menus::find($id);

        if (!Gate::allows('update-tenant-menu', [$menu, $tenant])) {
            return ResponseApi::error('Anda Bukan Pemilik Tenant Ini', 403);
        }

        $validationError = ValidationHelper::validate($request->all(), [
            'harga' => 'nullable|numeric|gt:0',
            'gambar' => 'nullable|mimes:png,jpg|max:2048',
            'nama_menu' => 'nullable',
            'deskripsi' => 'nullable',
            'kategori_id' => 'nullable',
            'isReady' => 'nullable'
        ]);
    
        if ($validationError) {
            return $validationError;
        }

        $url = $menu->gambar;
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        try {
            $menu->update([
                "tenant_id" => @$tenant->id ?? $menu->tenant_id,
                "kategori_id" => @$request->kategori_id ?? $menu->kategori_id,
                "harga" => @$request->harga ?? $menu->harga,
                "gambar" => @$url ?? $menu->gambar,
                "nama" => @$request->nama_menu ?? $menu->nama,
                "deskripsi" => @$request->deskripsi ?? $menu->deskripsi,
                "isReady" => @$request->isReady ?? $menu->isReady
            ]);

            return ResponseApi::success(compact('menu'), 'menu makanan berhasil diupdate');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return ResponseApi::serverError();
        }
    }

    public function updateMenuWeb(Request $request, $id)
    {
        $user = Auth::id();
        $tenant = Tenants::where("user_id", $user)->first();
        $menu = Menus::find($id);

        // if (!Gate::allows('update-tenant-menu', [$menu, $tenant])) {
        //     return ResponseApi::error('Anda Bukan Pemilik Tenant Ini', 403);
        // }

        $validator = Validator::make($request->all(), [
            'harga' => 'nullable|numeric|gt:0',
            'gambar' => 'nullable|mimes:png,jpg|max:2048',
            'nama_menu' => 'nullable',
            'deskripsi_menu' => 'nullable',
            'kategori_id' => 'nullable',
            'isReady' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'messages' => $validator->errors()->all()
            ]);
        }

        $url = $menu->gambar;
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        try {
            $menu->update([
                "tenant_id" => @$tenant->id ?? $menu->tenant_id,
                "kategori_id" => @$request->kategori_id ?? $menu->kategori_id,
                "harga" => @$request->harga ?? $menu->harga,
                "gambar" => @$url ?? $menu->gambar,
                "nama" => @$request->nama_menu ?? $menu->nama,
                "deskripsi" => @$request->deskripsi_menu,
                "isReady" => @$request->isReady ?? $menu->isReady
            ]);

            return response()->json([
                'status' => 'success',
                'messages' => 'berhasil update menu',
            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                'status' => 'gagal',
                "messages" => "Terjadi Kesalahan Pada Server"
            ], 500);
            //throw $th;
        }
    }

    public function destroyMenu(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->can('delete katalog')) {
            return ResponseApi::forbidden('tidak memiliki akses', 403);
        }
        
        try {
            $menu = $this->tenantService->destroyMenu($id);
            return ResponseApi::success(null, $menu);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseApi::error('Menu makanan tidak ditemukan', 404);
        } catch (\Exception $e) {
            return ResponseApi::error($e->getMessage(), 404);
        } catch (\Throwable $th) {
            return ResponseApi::serverError();
        }
    }

    public function showHistoryTransaksiTenant(Request $request)
    {
        $user = auth()->user();

        $tenantId = optional($user->tenant)->id;

        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak memiliki tenant terkait.'
            ], 403);
        }

        $filterDate = $request->input('filter_date');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransaksiDetail::selectRaw("
                tenants.nama_tenant,
                tenants.id,
                SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_1,
                SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) as pendapatan_kotor_2,
                (SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 1 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_1,
                (SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END) - 
                (0.1 * SUM(CASE WHEN transaksi.isAntar = 0 THEN transaksi_detail.harga * transaksi_detail.jumlah ELSE 0 END))) as pendapatan_bersih_2
            ")
            ->join('menus', 'transaksi_detail.menu_id', '=', 'menus.id')
            ->join('tenants', 'menus.tenant_id', '=', 'tenants.id')
            ->join('transaksi', 'transaksi_detail.transaksi_id', '=', 'transaksi.id')
            ->where('transaksi.status', 'selesai')
            ->where('tenants.id', $tenantId); 

        if ($filterDate) {
            $query->whereDate('transaksi.created_at', $filterDate);
        } elseif ($startDate && $endDate) {
            $query->whereBetween('transaksi.created_at', [$startDate, $endDate]);
        }

        $transaksiTenant = $query->groupBy('tenants.id', 'tenants.nama_tenant')->get();

        return response()->json([
            'status' => 'success',
            'data' => $transaksiTenant
        ]);
    }
}
