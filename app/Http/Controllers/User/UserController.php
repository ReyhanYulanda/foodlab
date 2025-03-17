<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Konfigurrasi\Menu;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
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
            'role' => $user->getRoleNames(),
            'menu' => $menu,
            'permission' => $permission,
            'isOnline' => $user->isOnline,
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

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json(compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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
            'isOnline' => ['nullable']
        ]);

        if($valdidator->fails()){
            return response()->json($valdidator->errors());
        }

        try{
            $updated = $user->update([
                "name" => @$request->name ?? $user->name,
                "email" => @$request->email ?? $user->email,
                "password" => @$request->password ?? $user->password,
                "isOnline" => @$request->isOnline ?? $user->isOnline,
            ]);

            if(!$updated){
                return response()->json(['messages' => 'Update Gagal']);
            }else{
                return response()->json([
                    'messages' => 'Update Berhasil',
                    'data' => $updated
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
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
