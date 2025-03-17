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
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->authorize('read tenant');
        $tenants = Tenants::with('pemilik')->get();
        return view('pages.konfigurasi.tenant.index', compact('tenants'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $users = User::all();
        return view('pages.konfigurasi.tenant.create', compact('users'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_tenant' => 'required',
            'nama_kavling' => 'required',
            'jam_buka' => 'required',
            'jam_tutup' => 'required',
            'pemilik' => 'required',
            'gambar' => 'nullable',
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
        ]);

        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil ditambahkan"]);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $users = User::all();
        $tenant = Tenants::find($id);
        return view('pages.konfigurasi.tenant.edit', compact('users', 'tenant'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $users = User::all();
        $tenant = Tenants::find($id);
        return view('pages.konfigurasi.tenant.edit', compact('users', 'tenant'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenants::find($id);
        
        $request->validate([
            'nama_tenant' => 'required',
            'nama_kavling' => 'required',
            'jam_buka' => 'required',
            'jam_tutup' => 'required',
            'pemilik' => 'required',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        // Cek apakah ada file gambar baru
        if ($request->hasFile('gambar')) {
            // Hapus gambar lama jika ada
            if ($tenant->nama_gambar) {
                Storage::delete(str_replace('/storage/', 'public/', $tenant->nama_gambar));
            }
    
            // Simpan gambar baru
            $gambar = $request->file('gambar');
            $path = $gambar->store('public/images');
            $url = Storage::url($path);
        } else {
            // Gunakan gambar lama jika tidak ada upload baru
            $url = $tenant->nama_gambar;
        }
    
        // Update data tenant
        $tenant->update([
            'nama_tenant' => $request->nama_tenant,
            'nama_kavling' => $request->nama_kavling,
            'jam_buka' => $request->jam_buka,
            'jam_tutup' => $request->jam_tutup,
            'user_id' => $request->pemilik,
            'nama_gambar' => $url, // Simpan gambar lama jika tidak ada perubahan
        ]);
    
        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil diupdate"]);
    }
    

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Tenants::find($id)->delete();
        return redirect()->route('tenant.index')->with(["status" => "success", 'message' => "Tenant berhasil dihapus"]);
    }
}
