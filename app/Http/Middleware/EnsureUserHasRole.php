<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `role:employee`, `role:support,admin`, ...
 *
 * - Guests are sent to the login page (or receive JSON 401 for API/JSON requests).
 * - Authenticated users whose role is not listed receive HTTP 403.
 * - Role names are validated against the UserRole enum (the single source of
 *   truth). A typo such as `role:suport` is a programming error and throws, so
 *   it can never silently lock everyone out or — worse — let someone in.
 * - There is NO implicit hierarchy: admin does not automatically satisfy
 *   `role:support`. List every allowed role explicitly.
 *
 * This is a coarse route-level gate. Record-level (ownership / assignment)
 * authorization is done by Policies — never rely on this middleware alone for
 * anything that touches a specific record.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = $this->parseRoles($roles);

        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if (! $user->is_active || ! $user->hasRole(...$allowed)) {
            abort(Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
        }

        return $next($request);
    }

    /**
     * @param  list<string>  $roles
     * @return non-empty-list<UserRole>
     */
    private function parseRoles(array $roles): array
    {
        $roles = array_values(array_filter(array_map('trim', $roles), fn (string $r) => $r !== ''));

        if ($roles === []) {
            throw new InvalidArgumentException('The role middleware needs at least one role, e.g. "role:admin".');
        }

        return array_map(
            fn (string $role) => UserRole::tryFrom($role)
                ?? throw new InvalidArgumentException(
                    "Unknown role [{$role}] in role middleware. Valid roles: ".implode(', ', UserRole::values()).'.'
                ),
            $roles,
        );
    }
}
