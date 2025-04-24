<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Tenant;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TenantController extends Controller
{
    public function index()
    {
        $this->authorize('read tenant');
        $tenants = Tenants::with('pemilik')->get();
        return view('pages.konfigurasi.tenant.index', compact('tenants'));
    }

    public function create()
    {
        $users = User::all();
        return view('pages.konfigurasi.tenant.create', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_tenant' => 'required',
            'nama_kavling' => 'required',
            'jam_buka' => 'nullable',
            'jam_tutup' => 'nullable',
            'pemilik' => 'required',
            'gambar' => 'nullable',
            'no_rekening_toko' => 'nullable',
            'no_rekening_pribadi' => 'nullable',
        ]);

        $url = null;
        if ($request->hasFile('gambar')) {
            $gambar = $request->file('gambar');

            $path = $gambar->store('public/images');

            $url = Storage::url($path);
        }

        Tenants::create([
            'nama_tenant' => $request->nama_tenant,
            'nama_kavling' => $request->nama_kavling,
            'jam_buka' => $request->jam_buka,
            'jam_tutup' => $request->jam_tutup,
            'user_id' => $request->pemilik,
            'nama_gambar' => $url,
            'no_rekening_toko' => $request->no_rekening_toko,
            'no_rekening_pribadi' => $request->no_rekening_pribadi,
        ]);

        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil ditambahkan"]);
    }

    public function show($id)
    {
        $users = User::all();
        $tenant = Tenants::find($id);
        return view('pages.konfigurasi.tenant.edit', compact('users', 'tenant'));
    }

    public function edit($id)
    {
        $users = User::all();
        $tenant = Tenants::find($id);
        return view('pages.konfigurasi.tenant.edit', compact('users', 'tenant'));
    }

    public function update(Request $request, $id)
    {
        $tenant = Tenants::find($id);
        
        $request->validate([
            'nama_tenant' => 'required',
            'nama_kavling' => 'required',
            'jam_buka' => 'required',
            'jam_tutup' => 'required',
            'pemilik' => 'required',
            'no_rekening_toko' => 'nullable',
            'no_rekening_pribadi' => 'nullable',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        if ($request->hasFile('gambar')) {
            if ($tenant->nama_gambar) {
                Storage::delete(str_replace('/storage/', 'public/', $tenant->nama_gambar));
            }
    
            $gambar = $request->file('gambar');
            $path = $gambar->store('public/images');
            $url = Storage::url($path);
        } else {
            $url = $tenant->nama_gambar;
        }
    
        $tenant->update([
            'nama_tenant' => $request->nama_tenant,
            'nama_kavling' => $request->nama_kavling,
            'jam_buka' => $request->jam_buka,
            'jam_tutup' => $request->jam_tutup,
            'user_id' => $request->pemilik,
            'no_rekening_toko' => $request->no_rekening_toko,
            'no_rekening_pribadi' => $request->no_rekening_pribadi,
            'nama_gambar' => $url,
        ]);
    
        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil diupdate"]);
    }
    

    public function destroy($id)
    {
        Tenants::find($id)->delete();
        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil dihapus"]);
    }
}
