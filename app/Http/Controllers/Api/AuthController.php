<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials([
            'email' => (string) $request->validated('email'),
            'is_active' => true,
        ]);

        if (! $user || ! $provider->validateCredentials($user, ['password' => (string) $request->validated('password')])) {
            RateLimiter::hit($request->throttleKey());

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($request->throttleKey());
        $token = $user->createToken('servicedesk-api', ['*'], config('sanctum.expiration') ? now()->addMinutes(config('sanctum.expiration')) : null);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('department')),
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ],
        ], 201);
    }

    public function user(): JsonResponse
    {
        return response()->json(['data' => new UserResource(request()->user()->load('department'))]);
    }

    public function logout(): JsonResponse
    {
        $token = request()->user()->currentAccessToken();
        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return response()->json(['data' => ['message' => 'Token revoked.']]);
    }
}
