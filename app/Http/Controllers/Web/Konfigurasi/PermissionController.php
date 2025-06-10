<?php

namespace App\Http\Controllers\Web\Konfigurasi;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search'); 
        $query = Permission::query();

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $permissions = $query->paginate($perPage);
        return view('pages.konfigurasi.permission', compact('permissions'));
    }

    public function create()
    {
        return view('pages.konfigurasi.tambah-permission');
    }

    public function store(Request $request, Permission $permission)
    {
        $request->validate(['name' => 'required|unique:permissions']);
        $permission->name =  $request->name;
        $permission->save();
        return redirect()->route('permission.index')->with(["status" => "success", 'message' => "Permission berhasil ditambahkan"]);
    }

    public function destroy($id)
    {
        Permission::find($id)->delete();
        return redirect()->route('permission.index')->with(["status" => "success", 'message' => "Permission berhasil dihapus"]);
    }
}
