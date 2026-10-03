<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // `active` and `role` must run BEFORE route-model binding. Otherwise a
        // user who is not allowed to see /admin/users/{user} would get 404 for
        // ids that do not exist but 403 for ids that do — letting them probe
        // which records exist. (Order: auth -> active -> role -> bindings.)
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureUserHasRole::class);
        $middleware->prependToPriorityList(before: EnsureUserHasRole::class, prepend: EnsureUserIsActive::class);

        // A password change (reset, or "change password") invalidates every
        // OTHER session of that user: the session stores a hash of the password
        // and is logged out when it no longer matches.
        $middleware->authenticateSessions();

        // Signed-in users who open /login etc. are sent to the landing page.
        $middleware->redirectUsersTo(fn () => route('home'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['error' => [
                'code' => 'validation_failed',
                'message' => 'The given data was invalid.',
                'details' => $exception->errors(),
            ]], 422);
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return $request->is('api/*')
                ? response()->json(['error' => ['code' => 'unauthenticated', 'message' => 'Unauthenticated.']], 401)
                : null;
        });
        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            return $request->is('api/*')
                ? response()->json(['error' => ['code' => 'forbidden', 'message' => 'This action is unauthorized.']], 403)
                : null;
        });
        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            return $request->is('api/*')
                ? response()->json(['error' => ['code' => 'not_found', 'message' => 'The requested resource was not found.']], 404)
                : null;
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                422 => 'validation_failed',
                429 => 'too_many_requests',
                default => 'request_failed',
            };
            $message = match ($status) {
                401 => 'Unauthenticated.',
                403 => 'This action is forbidden.',
                404 => 'The requested resource was not found.',
                429 => 'Too many requests.',
                default => 'The request could not be completed.',
            };

            return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            return $request->is('api/*')
                ? response()->json(['error' => ['code' => 'server_error', 'message' => 'The request could not be completed.']], 500)
                : null;
        });
    })->create();
