<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }

    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Cari user by email
        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email is already verified.'], 400);
        }

        // Cek apakah user sedang dalam cooldown
        $cacheKey = 'email_verification_sent:' . $user->id;
        if (Cache::has($cacheKey)) {
            $remaining = Cache::get($cacheKey) - now()->timestamp;
            return response()->json([
                'message' => 'Please wait before requesting another verification email.',
                'retry_after_seconds' => $remaining > 0 ? $remaining : 0,
            ], 429);
        }

        // Kirim email verifikasi
        $user->sendEmailVerificationNotification();

        // Set cooldown selama 2 menit (120 detik)
        Cache::put($cacheKey, now()->addMinutes(2)->timestamp, now()->addMinutes(2));

        return response()->json(['message' => 'Verification email sent successfully.']);
    }
}
