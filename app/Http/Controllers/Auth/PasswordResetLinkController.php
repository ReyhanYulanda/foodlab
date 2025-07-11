<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $cacheKey = 'password_reset_cooldown_' . md5($email);

        if (Cache::has($cacheKey)) {
            return response()->json([
                'message' => 'Tunggu beberapa menit sebelum mencoba lagi.'
            ], 429);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 400);
        }

        Cache::put($cacheKey, true, now()->addMinutes(5));
        return response()->json(['message' => 'Link reset password telah dikirim.']);
    }

    public function passwordResetAPI(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');
        $cacheKey = 'password_reset_cooldown_' . md5($email);

        if (Cache::has($cacheKey)) {
            return response()->json([
                'message' => 'Tunggu beberapa menit sebelum mencoba lagi.'
            ], 429);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status != Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)], 400);
        }

        // Set cooldown 5 menit
        Cache::put($cacheKey, true, now()->addMinutes(5));

        return response()->json(['message' => 'Link reset password telah dikirim.']);
    }
}
