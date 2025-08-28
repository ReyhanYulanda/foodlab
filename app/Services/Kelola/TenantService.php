<?php

namespace App\Services\Kelola;

use App\Models\Menus;
use App\Models\Tenants;
use App\Models\TransaksiDetail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class TenantService
{
    public function getTenantData($user)
    {
        $tenant = Tenants::where('user_id', $user->id)
            ->with('listMenu')
            ->with('pemilik')
            ->first();

        if ($tenant) {
            $transaksiBerhasil = TransaksiDetail::whereHas('menus', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            })
                ->where('status', 'selesai')
                ->count();

            $tenant->transaksi_berhasil = $transaksiBerhasil;
        }
        return $tenant;
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

    public function interuptBusy(Tenants $tenant): Tenants
    {

        if ($tenant->busy_until == null) {
            return $tenant;
        }
        
        $now = now();

        // Update interrupt + busy_until
        $tenant->update([
            'is_interupt' => $now,
            'busy_until'  => $now->copy()->addMinutes(3),
        ]);

        // Cek user pemilik
        if ($tenant->pemilik) {
            $user = $tenant->pemilik;

            // Validasi jam operasional
            if (
                $user->isOnline == 0
                && $tenant->jam_buka
                && $tenant->jam_tutup
                && $now->between($tenant->jam_buka, $tenant->jam_tutup)
            ) {

                $user->update(['isOnline' => 1]);
            }
        }

        return $tenant;
    }
}
