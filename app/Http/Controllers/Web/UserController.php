<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('pages.konfigurasi.user.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('pages.konfigurasi.user.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_user' => 'required',
            'email' => 'required',
            'roles' => 'nullable',
            'phone' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $password = bcrypt('12345678');

        $url = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $path = $image->store('public/images');

            $url = Storage::url($path);
        }

        $user = User::create([
            'name' => $request->nama_user,
            'email' => $request->email,
            'password' => $password,
            'image' => $url,
        ]);

        if($request->roles){
            foreach($request->roles as $role){
                $user->assignRole($role);
            }
        }

        return redirect()->route('user.index')->with(["status" => "success", 'message' => "User berhasil ditambahkan"]);
    }

    public function edit($id)
    {
        $roles = Role::all();
        $user = User::find($id);
        return view('pages.konfigurasi.user.edit', compact('roles', 'user'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'nama_user' => 'required',
            'email' => 'required',
            'roles' => 'nullable',
            'phone' => 'nullable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = User::find($id);

        $url = $user->image;
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $path = $image->store('public/images');

            $url = Storage::url($path);
        }

        $user->update([
            'name' => $request->nama_user,
            'email' => $request->email,
            'phone' => $request->phone,
            'image' => $url,
        ]);

        if($request->roles){
            $user->syncRoles($request->roles);
        }

        return redirect()->route('user.index')->with(["status" => "success", 'message' => "User berhasil diupdate"]);;
    }

    public function destroy($id)
    {
        $user = User::destroy($id);
        return redirect()->route('user.index')->with(["status" => "success", 'message' => "User berhasil dihapus"]);;
    }
}
