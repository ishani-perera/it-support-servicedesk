<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Authenticated "change my password". Requires the current password, then
     * signs out every OTHER device/session while keeping this one.
     */
    public function update(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $request->user()->forceFill(['password' => $validated['password']])->save();
        $request->user()->tokens()->delete();

        Auth::logoutOtherDevices($validated['password']);

        return $request->wantsJson()
            ? response()->noContent()
            : back()->with('status', 'password-updated');
    }
}
