<?php

namespace App\Http\Controllers\Auth;

use App\Mail\ResetPasswordMail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    public function passwordResetAPI(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Cari user dulu
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Generate token reset password
        $token = Password::createToken($user);

        // Kirim email manual pakai Mail::to() + bcc
        Mail::to($user->email)
            ->bcc('support@foodlabpens.com')
            ->send(new ResetPasswordMail($token));

        return response()->json(['message' => 'We have emailed your password reset link!']);
    }
}
