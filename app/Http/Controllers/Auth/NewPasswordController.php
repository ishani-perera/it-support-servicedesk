<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailField;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /**
     * The broker checks that the token was issued for THIS e-mail address,
     * has not expired (config auth.passwords.users.expire) and is unused, so
     * a token can never reset someone else's password. The token is deleted
     * after use.
     *
     * After a successful reset the remember-me token is rotated and the
     * password hash changes, which signs out every other session of the user
     * (AuthenticateSession middleware). The user then logs in with the new
     * password — they are not auto-logged-in from an e-mail link.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => EmailField::rules(),
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            [
                'email' => (string) $request->input('email'),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $request->input('token'),
                'is_active' => true,
            ],
            function (User $user) use ($request) {
                // The model's `hashed` cast hashes the plaintext with the configured hasher.
                $user->forceFill([
                    'password' => (string) $request->input('password'),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        // Deliberately one generic message for invalid/expired token, unknown
        // e-mail and inactive account alike.
        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'This password reset link is invalid or has expired.']);
    }
}
