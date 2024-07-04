<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\Menus;
use App\Models\Tenants;
use App\Response\ResponseApi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->can('read kelola tenant')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenant = Tenants::where('user_id', $user->id)->with('listMenu')->first();
        return response()->json([
            'status' => 'success',
            'message' => 'berhasil mengambil data',
            'data' => [
                'tenant' => $tenant
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeMenu(Request $request)
    {
        $user = $request->user();

        if (!$user->can('create katalog')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenant = Tenants::where("user_id", $user->id)->first();

        $validator = Validator::make($request->all(), [
            // 'menu_id' => 'required',
            'harga' => 'required|numeric',
            'nama_menu' => 'required',
            'deskripsi_menu' => 'nullable',
            'kategori_id' => 'required',
            'gambar' => 'nullable|mimes:png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "status" => "Bad Request",
                "message" => $validator->errors()->all()
            ], 400);
        }

        $url = '/assets/images/default-image.jpg';
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        // $menu = Menus::find($request->menu_id);

        try {
            $newMenu = Menus::create([
                "harga" => $request->harga,
                "gambar" => $url,
                "nama" => $request->nama_menu,
                "deskripsi" => @$request->deskripsi_menu,
                "tenant_id" => @$tenant->id,
                "kategori_id" => $request->kategori_id,
            ]);

            return response()->json([
                "status" => "success",
                "message" => "menu makanan berhasil ditambahkan",
                "data" => $newMenu
            ]);
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return response()->json([
                "status" => "failed",
                "message" => ResponseApi::serverError(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $user = $request->user();

        if (!$user->can('update kelola tenant')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenant = Tenants::where("user_id", $user->id)->first();
        // Gate::authorize('update-tenant', $tenant);

        $validator = Validator::make($request->all(), [
            'nama_kavling' => 'required',
            'nama_tenant' => 'required',
            'gambar' => 'nullable|mimes:png,jpg|max:2048',
            'jam' => 'required|timezone'
        ]);

        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        if ($validator->fails()) {
            return response()->json([
                'messages' => $validator->errors()->all()
            ]);
        }

        $update = $tenant->update([
            'nama_kavling' => $request->input('nama_kavling'),
            'nama_tenant' => $request->input('nama_tenant'),
            'jam' => Carbon::parse($request->input('jam'))->format('H:i:s'),
            'nama_gambar' => $url,
        ]);

        if (!$update) {
            return response()->json([
                'status' => 'success',
                'messages' => 'berhasil update tenant',
            ], 500);
        }

        return response()->json([
            'messages' => 'berhasil'
        ]);
    }
    public function updateMenu(Request $request, $id)
    {
        $user = $request->user();

        if (!$user->can('update katalog')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }

        $tenant = Tenants::where("user_id", $request->user()->id)->first();
        $menu = Menus::find($id);

        if (!Gate::allows('update-tenant-menu', [$menu, $tenant])) {
            return ResponseApi::error('Anda Bukan Pemilik Tenant Ini', 403);
        }

        $validator = Validator::make($request->all(), [
            // 'menu_id' => 'nullable',
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
                // "menu_id" => @$request->menu_id ?? $menu->menu_id,
                "tenant_id" => @$tenant->id,
                "kategori_id" => @$request->kategori_id ?? $menu->kategori_id,
                "harga" => @$request->harga ?? $menu->harga,
                "gambar" => @$url,
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroyMenu(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->can('delete katalog')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'tidak memiliki akses',
            ], 403);
        }
        try {
            $menu = Menus::find($id)->delete();

            return response()->json([
                "status" => "success",
                "message" => "data berhasil dihapus",
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                "status" => "failed",
                "message" => $th->getMessage(),
            ], 500);
        }
    }
}
