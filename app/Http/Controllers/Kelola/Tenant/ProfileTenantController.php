<?php

namespace App\Http\Controllers\Kelola\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Helper\ValidationHelper;
use Illuminate\Http\Request;

class ProfileTenantController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant; 
    
        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found for this user'], 404);
        }
    
        return response()->json([
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found for this user'], 404);
        }

        $validationError = ValidationHelper::validate($request->all(), [
            'nama_tenant' => 'nullable|string|max:255',
            'nama_kavling' => 'nullable|string|max:255',
            'jam_buka' => 'nullable',
            'jam_tutup' => 'nullable',
            'no_rekening_toko' => 'nullable|string|max:255',
            'no_rekening_pribadi' => 'nullable|string|max:255',
            'gambar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    
        if ($validationError) {
            return $validationError;
        }

        // Handle gambar upload jika ada
        if ($request->hasFile('gambar')) {
            // Hapus gambar lama jika ada
            if ($tenant->nama_gambar) {
                Storage::delete(str_replace('/storage/', 'public/', $tenant->nama_gambar));
            }

            $path = $request->file('gambar')->store('public/images');
            $url = Storage::url($path);
        } else {
            $url = $tenant->nama_gambar;
        }

        $tenant->update([
            'nama_tenant' => $request->nama_tenant,
            'nama_kavling' => $request->nama_kavling,
            'jam_buka' => $request->jam_buka,
            'jam_tutup' => $request->jam_tutup,
            'no_rekening_toko' => $request->no_rekening_toko,
            'no_rekening_pribadi' => $request->no_rekening_pribadi,
            'nama_gambar' => $url,
        ]);

        return response()->json(['message' => 'Tenant profile updated successfully', 'tenant' => $tenant]);
    }
}
