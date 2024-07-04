<?php

namespace App\Http\Controllers\Api;

use App\Helper\RoleHelper;
use App\Http\Controllers\Controller;
use App\Models\Konfigurrasi\Menu;
use App\Models\Role;
use App\Models\User;
use App\Response\ResponseApi;
use App\Services\Firebases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
// use Silber\Bouncer\Bouncer;
use Silber\Bouncer\BouncerFacade;
use Throwable;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|unique:users,email|email|regex:/^\S*$/',
            'password' => ['required', Password::min(8)->letters()],
            'name' => 'required|regex:/^[a-zA-Z\s]+$/|max:25',
            'role' => 'nullable'
        ]);

        if ($validate->fails()) {
            return ResponseApi::error($validate->errors()->all(), 422);
        }

        DB::beginTransaction();
        try{
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Token Management
            $token = $newUser->createToken('secret')->plainTextToken;

            if ($request->role) {
                // check role ada di database
                RoleHelper::isRoleExist($request->role);

                $newUser->assignRole($request->role);
            } else {
                $newUser->assignRole('user');
            }
            DB::commit();
        }catch(Throwable $th){
            DB::rollBack();
            Log::error($th->getMessage());

            ResponseApi::serverError();
        }

        $data = [
            'name' => $newUser->name,
            'token' => $token,
            'token_type' => 'Bearer'
        ];

        return ResponseApi::success($data, 'Berhasil Mendaftar');
    }
    public function login(Request $request, Firebases $firebases)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required',
        ]);

        if ($validate->fails()) {
            return ResponseApi::error($validate->errors()->all(), 422);
        }

        if (!Auth::attempt($request->only(['email', 'password']))) {
            return ResponseApi::error('email atau password salah');
        }

        $user = User::where('email', $request->email)->first();

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
        ];

        ($firebases->updateFcmToken($user, $request->fcm_token));

        return ResponseApi::success($data, 'berhasil mendapatkan data');
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return ResponseApi::success(null, 'logout berhasil');
    }
}
