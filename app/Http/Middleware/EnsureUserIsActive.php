<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware alias `active`.
 *
 * Login already refuses deactivated accounts, but a user can be deactivated
 * WHILE signed in (or hold a Sanctum token). This closes that window: on the
 * very next request the session is destroyed (web) or the request is refused
 * (JSON/API). Place it AFTER the `auth` / `auth:sanctum` middleware.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            // Token-authenticated API request: refuse, keep it stateless.
            abort(Response::HTTP_FORBIDDEN, 'This account is deactivated.');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => 'This account is not available. Please contact IT.',
        ]);
    }
}
