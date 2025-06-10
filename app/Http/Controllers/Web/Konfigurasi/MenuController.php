<?php

namespace App\Http\Controllers\Web\Konfigurasi;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Konfigurrasi\Menu;
use App\Models\Konfigurrasi\MenuPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Traits\HasMenuPermission;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    use HasMenuPermission;
    public function index(request $request)
    {
        $this->authorize('read menu');
        $perPage = $request->input('per_page', 10); 
        $search = $request->input('search'); 

        $query = Menu::query();

        if ($search) {
            $query->where('nama', 'like', "%{$search}%")
                ->orWhere('url', 'like', "%{$search}%");
        }

        $menu = $query->paginate($perPage);

        return view('pages.konfigurasi.menu', compact('menu'));
    }

    public function create(Menu $menu)
    {
        $roles = Role::get();
        $devices = Device::all();
        return view('pages.konfigurasi.tambah-menu', compact('menu', 'roles', 'devices'));
    }

    public function store(Request $request, Menu $menu)
    {
        $menu->nama = $request->name;
        $menu->url = $request->url;;
        $menu->kategori = $request->category;
        $menu->ikon = $request->icon;
        $menu->save();

        $menu->device()->sync($request->device_id);
        $this->attachMenuPermission($menu, $request->permissions ?? [], []);

        return redirect()->route('menu.index')->with(["status" => "success", 'message' => "Menu berhasil ditambahkan"]);
    }

    public function show(Menu $menu)
    {
        return view('pages.konfigurasi.lihat-permission-menu', [
            'permissions' => $menu->permissions,
            'menu' => $menu,
        ]);
    }

    public function edit(Menu $menu)
    {
        $roles = Role::get();
        $devices = Device::all();
        return view('pages.konfigurasi.edit-menu', compact('menu', 'devices'));
    }

    public function update(Request $request, Menu $menu)
    {
        $menuPermission = MenuPermission::where('menu_id', $menu->id);
        $permissions = Permission::whereIn('id', $menuPermission->pluck('permission_id')->toArray());
        $roles = (Role::whereHas('permissions', function($permissionis)  use ($permissions){
            $permissionis->whereIn('id', $permissions->pluck('id')->toArray());
        })->get());

        if ($menu->name != $request->nama) {
            $menuPermission->delete();
            $permissions->delete();
        }

        $menu->nama = $request->name;
        $menu->url = $request->url;
        $menu->kategori = $request->category;
        $menu->ikon = $request->icon;
        $menu->save();

        $menu->device()->sync($request->device_id);
        $permissions = [];
        foreach ($request->permissions ?? [] as $value) {
            $permission = Permission::firstOrCreate(['name' => $value . " {$menu->nama}"], ['name' => $value . " {$menu->nama}"]);
            $permissions[] = $permission->id;
        }

        $sync = ($menu->permissions()->sync($permissions));
        $this->syncRolePermission($roles, $sync);

        return redirect()->route('menu.index')->with(["status" => "success", 'message' => "Menu berhasil diupdate"]);
    }

    public function destroy(Menu $menu)
    {
        $menuPermission = MenuPermission::where('menu_id', $menu->id);
        $permissions = Permission::whereIn('id', $menuPermission->pluck('permission_id')->toArray());

        $roles = Role::whereHas('permissions', function ($query) use ($permissions) {
            $query->whereIn('id', $permissions->pluck('id')->toArray());
        })->get();

        $roles->each(function ($role) use ($permissions) {
            $role->revokePermissionTo($permissions->get());
        });

        $menuPermission->delete();
        $permissions->delete();
        $menu->delete();

        return redirect()->route('menu.index')->with(["status" => "success", 'message' => "Menu berhasil dihapus"]);
        ;
    }

    public function syncRolePermission($roles, $sync){
        foreach($roles ?? [] as $role){
            $role->permissions()->attach($sync["attached"]);
            $role->permissions()->detach($sync["detached"]);
        }
        Permission::whereIn('id', $sync['detached'])->delete();
    }
}
