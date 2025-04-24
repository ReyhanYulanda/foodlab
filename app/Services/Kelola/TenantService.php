<?php

namespace App\Services\Kelola;

use App\Models\Menus;
use App\Models\Tenants;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class TenantService
{
    public function getTenantData($user)
    {
        return Tenants::where('user_id', $user->id)->with('listMenu')->with('pemilik')->first();
    }

    public function storeMenu(Request $request, $user)
    {
        $tenant = Tenants::where("user_id", $user->id)->first();

        $url = '/assets/images/default-image.jpg';
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        return Menus::create([
            "harga" => $request->harga,
            "gambar" => $url,
            "nama" => $request->nama_menu,
            "deskripsi" => $request->deskripsi_menu,
            "tenant_id" => $tenant->id,
            "kategori_id" => $request->kategori_id,
        ]);
    }

    public function updateMenu($menu, $tenant, $request)
    {
        $url = $menu->gambar;
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        $menu->update([
            "tenant_id" => @$tenant->id,
            "kategori_id" => @$request->kategori_id ?? $menu->kategori_id,
            "harga" => @$request->harga ?? $menu->harga,
            "gambar" => @$url,
            "nama" => @$request->nama_menu ?? $menu->nama,
            "deskripsi" => @$request->deskripsi_menu,
            "isReady" => @$request->isReady ?? $menu->isReady
        ]);

        return $menu;
    }

    public function destroyMenu($id)
    {
        $menu = Menus::find($id); // Menggunakan find() agar tidak melemparkan exception

        if (!$menu) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Menu makanan tidak ditemukan');
        }

        $menu->delete();
        
        return 'Menu makanan berhasil dihapus';
    }
}