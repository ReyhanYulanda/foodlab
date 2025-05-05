<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Konfigurrasi\Menu;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if(!$user){
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada akses'
            ], 403);
        }

        $user = User::find($user->id);

        $menu = Menu::whereHas('device', function($device){
            return $device->where('device_id', 1);
        })->orderby('urutan')->get();

        $menu = $menu->filter(function ($mm) use ($user) {
            if ($user->can('read ' . $mm->nama)) {
                return $mm;
            }
        })->values();

        $permission = ($user->getPermissionsViaRoles()->pluck('name')->toArray());

        $token = $user->createToken('secret', $permission)->plainTextToken;
        $data = [
            'nama' => $user->name,
            'email' => $user->email,
            'token' => $token,
            'token_type' => 'Bearer',
            'isOnline' => $user->isOnline,
            'phone' => $user->phone,
            'gambar' => $user->image,
            'role' => $user->getRoleNames(),
            'menu' => $menu,
            'permission' => $permission,
        ];

        if(!$user){
            return response()->json([
                'status' => 'failed',
                'message' => 'pengguna tidak ditemukan'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil mendapatkan data',
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $valdidator = Validator::make($request->all(), [
            'name' => ['required', 'string'],
            'email'=>['unique:users,email'],
            'password'=>'required'
        ]);

        if($valdidator->fails()){
            return response()->json([
                'messages' => $valdidator->errors(),
            ]);
        }

        $newUser = User::create([
            'nama' => $request->nama,
            'email' => $request->nama,
            'password' => Hash::make($request->password),
        ]);

        return $newUser;
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json(compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if(!$user){
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak ada akses'
            ], 403);
        }

        $user = User::find($user->id);

        $valdidator = Validator::make($request->all(), [
            'name' => ['string', 'nullable'],
            'email'=> ['unique:users,email', 'nullable'],
            'password'=> 'nullable',
            'phone'=> 'nullable',
            'isOnline' => ['nullable'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        if($valdidator->fails()){
            return response()->json($valdidator->errors());
        }

        $url = $user->image;
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            $path = $image->store('public/images');

            $url = Storage::url($path);
        }

        try{
            $data = [
                "name" => $request->name ?? $user->name,
                "email" => $request->email ?? $user->email,
                "password" => $request->password ? Hash::make($request->password) : $user->password,
                "phone" => $request->phone ?? $user->phone,
                "isOnline" => $request->isOnline ?? $user->isOnline,
                "image" => @$url ?? $user->image,
            ];
    
            $user->update($data);  

            if(!$user){
                return response()->json(['messages' => 'Update Gagal']);
            }else{
                return response()->json([
                    'messages' => 'Update Berhasil',
                    'data' => $user
                ]);
            }
        }catch(Exception $e){
            return ResponseApi::serverError();
        }
    }
    
    public function updateFcmToken(Request $request, Firebases $firebases)
    {
        $user = $request->user();

        $valdidator = Validator::make($request->all(), [
            'fcm_token' => 'string'
        ]);

        if($valdidator->fails()){
            return response()->json($valdidator->errors()->all());
        }

        $updated = $firebases->updateFcmToken($user->id, $request->token);

        if(!$updated){
            return response()->json(['messages' => 'Update Gagal']);
        }else{
            return response()->json([
                'messages' => 'Update Berhasil',
                'data' => $updated
            ]);
        }
    }

    public function destroy($id)
    {
        $deleted = User::findOrFail($id)->delete();
        if(!$deleted){
            return response()->json(['messages' => 'Update Gagal']);
        }else{
            return response()->json([
                'messages' => 'Delete Berhasil',
                'data' => $deleted
            ]);
        }
    }
}
