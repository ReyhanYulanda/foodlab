<?php

namespace App\Http\Controllers\Api;

use App\Helper\RoleHelper;
use App\Http\Controllers\Controller;
use App\Models\FcmToken;
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
use Illuminate\Support\Str;
use Google\Client as GoogleClient;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|unique:users,email|email|regex:/^\S*$/',
            'password' => ['required', Password::min(8)->letters()],
            'name' => 'required|regex:/^[a-zA-Z\s]+$/|max:25',
            // 'phone' => 'required|regex:/^\+?[0-9\s]+$/',
        ]);

        if ($validate->fails()) {
            return ResponseApi::error($validate->errors()->all(), 422);
        }

        // ✅ Daftar domain yang diperbolehkan
        $allowedDomains = ['gmail.com', 'yahoo.com', 'pens.ac.id'];

        // Ambil domain dari email user
        $emailDomain = substr(strrchr($request->email, "@"), 1);

        // Cek apakah domain ada di daftar
        if (!in_array(strtolower($emailDomain), $allowedDomains)) {
            return ResponseApi::error(['Email domain tidak diizinkan. Gunakan email @gmail.com, @yahoo.com, atau @pens.ac.id'], 422);
        }

        DB::beginTransaction();
        try {
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                // 'phone' => $request->phone,
            ]);
            $newUser->sendEmailVerificationNotification();

            // Token Management
            $token = $newUser->createToken('secret')->plainTextToken;

            // Hardcode assign role 'user'
            $newUser->assignRole('user');

            DB::commit();
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());

            return ResponseApi::serverError();
        }

        $data = [
            'name' => $newUser->name,
            'token' => $token,
            'token_type' => 'Bearer'
        ];

        return ResponseApi::success($data, 'Berhasil Mendaftar, silahkan verifikasi email Anda');
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

        if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Silakan cek email untuk verifikasi akun Anda.',
            ], 403);
        }

        $menu = Menu::whereHas('device', function ($device) {
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

        if ($request->filled('fcm_token')) {
            FcmToken::updateOrCreate([
                'user_id' => $user->id,
                'fcm_token' => $request->fcm_token,
            ], [
                'device_id' => $request->device_id ?? null,
            ]);
        }

        return ResponseApi::success($data, 'berhasil mendapatkan data');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $fcmToken = $request->input('fcm_token'); // pastikan dikirim dari frontend

        if ($fcmToken) {
            $user->fcmTokens()->where('fcm_token', $fcmToken)->delete();
        }

        $user->currentAccessToken()->delete();

        return ResponseApi::success(null, 'Logout berhasil');
    }

    public function forgotPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validate->fails()) {
            return ResponseApi::error($validate->errors()->all(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ResponseApi::error('Email tidak ditemukan', 404);
        }

        // Here you would typically send a password reset link to the user's email
        // For simplicity, we will just return a success message
        return ResponseApi::success(null, 'Link reset password telah dikirim ke email Anda');
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $idToken = $request->input('id_token');

        $client = new GoogleClient(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($idToken);

        if (!$payload) {
            return ResponseApi::error('Invalid Google token', 401);
        }

        $googleId     = $payload['sub'];
        $googleEmail  = $payload['email'];
        $googleName   = $payload['name'] ?? $googleEmail;
        $googleAvatar = $payload['picture'] ?? null;

        try {
            $user = User::where('google_id', $googleId)->first();

            if (!$user) {
                $user = User::where('email', $googleEmail)->first();

                if ($user) {
                    $user->update([
                        'google_id' => $googleId,
                        'image'     => $googleAvatar,
                    ]);
                } else {
                    $user = User::create([
                        'name'      => $googleName,
                        'email'     => $googleEmail,
                        'google_id' => $googleId,
                        'image'     => $googleAvatar,
                        'password'  => bcrypt(Str::random(16)),
                        'email_verified_at' => now(), // auto verified, karena dari Google
                    ]);
                    $user->assignRole('user'); // assign role user
                }
            }

            Auth::login($user);

            // ambil menu sesuai permission
            $menu = Menu::whereHas('device', function ($device) {
                return $device->where('device_id', 1);
            })->orderby('urutan')->get();

            $menu = $menu->filter(function ($mm) use ($user) {
                if ($user->can('read ' . $mm->nama)) {
                    return $mm;
                }
            })->values();

            $permission = $user->getPermissionsViaRoles()->pluck('name')->toArray();

            $token = $user->createToken('secret', $permission)->plainTextToken;

            // update FCM kalau ada
            if ($request->filled('fcm_token')) {
                FcmToken::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'fcm_token' => $request->fcm_token,
                    ],
                    [
                        'device_id' => $request->device_id ?? null,
                    ]
                );
            }

            $data = [
                'nama'       => $user->name,
                'email'      => $user->email,
                'token'      => $token,
                'token_type' => 'Bearer',
                'role'       => $user->getRoleNames(),
                'menu'       => $menu,
                'permission' => $permission,
            ];

            return ResponseApi::success($data, 'Login dengan Google berhasil');
        } catch (Throwable $th) {
            Log::error($th->getMessage());
            return ResponseApi::serverError();
        }
    }
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // cek user by google_id
            $user = User::where('google_id', $googleUser->getId())->first();

            if (!$user) {
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'image'     => $googleUser->getAvatar(),
                    ]);
                } else {
                    $user = User::create([
                        'name'              => $googleUser->getName(),
                        'email'             => $googleUser->getEmail(),
                        'google_id'         => $googleUser->getId(),
                        'image'             => $googleUser->getAvatar(),
                        'password'          => bcrypt(Str::random(16)),
                        'email_verified_at' => now(), // auto verified karena Google sudah validasi email
                    ]);
                    $user->assignRole('user');
                }
            }

            Auth::login($user);

            // buat token Sanctum untuk API access (optional)
            $permission = $user->getPermissionsViaRoles()->pluck('name')->toArray();
            $token = $user->createToken('secret', $permission)->plainTextToken;

            // redirect ke frontend / dashboard dengan token
            return redirect()->away("https://staging.foodlabpens.com/dashboard?token={$token}");
        } catch (Throwable $th) {
            Log::error('Google Login Error: ' . $th->getMessage());
            return redirect()->route('login')->with('error', 'Login Google gagal, coba lagi.');
        }
    }
}
