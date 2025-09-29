<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\DriverDetail;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search'); 

        $query = User::with('roles');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($perPage);
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
                if ($role === 'masbro') {
                    DriverDetail::firstOrCreate(
                        ['user_id' => $user->id],
                        ['no_rekening' => null]
                    );
                }
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
        
            if (in_array('masbro', $request->roles)) {
                DriverDetail::firstOrCreate(
                    ['user_id' => $user->id],
                    ['no_rekening' => null]
                );
            }
        }        

        return redirect()->route('user.index')->with(["status" => "success", 'message' => "User berhasil diupdate"]);;
    }

    public function destroy($id)
    {
        $user = User::destroy($id);
        return redirect()->route('user.index')->with(["status" => "success", 'message' => "User berhasil dihapus"]);;
    }
}
