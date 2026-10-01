<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\EmailField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Sends a reset link through Laravel's password broker (hashed, single-use,
     * expiring tokens in `password_reset_tokens`).
     *
     * The response is IDENTICAL whether or not the address belongs to an active
     * account, and whether or not the broker throttled the request, so this
     * form cannot be used to discover which e-mail addresses are registered.
     * Deactivated accounts never receive a link.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => EmailField::rules()]);

        Password::sendResetLink([
            'email' => (string) $request->input('email'),
            'is_active' => true,
        ]);

        return back()->with('status', 'If that e-mail address belongs to an active account, a password reset link has been sent.');
    }
}
