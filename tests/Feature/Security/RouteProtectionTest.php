<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Static audit of the route table: the application is closed by default.
 * Adding a new public route requires adding it to PUBLIC below on purpose.
 */
class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that may be reached without logging in: "METHOD uri". */
    private const PUBLIC = [
        'GET /',
        'GET up',
        'GET sanctum/csrf-cookie',          // Sanctum SPA CSRF endpoint (package route)
        'POST api/auth/login',
        'GET login', 'POST login',
        'GET forgot-password', 'POST forgot-password',
        'GET reset-password/{token}', 'POST reset-password',
    ];

    /**
     * @return list<array{0: string, 1: LaravelRoute}>
     */
    private function routes(): array
    {
        $all = [];

        foreach (Route::getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $all[] = ["{$method} {$route->uri()}", $route];
            }
        }

        return $all;
    }

    public function test_every_route_is_either_explicitly_public_or_requires_auth_and_an_active_account(): void
    {
        foreach ($this->routes() as [$key, $route]) {
            if (in_array($key, self::PUBLIC, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();

            $this->assertTrue(
                collect($middleware)->contains(fn ($m) => is_string($m) && ($m === 'auth' || str_starts_with($m, 'auth:'))),
                "{$key} is reachable without authentication (middleware: ".implode(', ', array_filter($middleware, 'is_string')).')',
            );
            $this->assertContains('active', $middleware, "{$key} does not check that the account is active");
        }
    }

    public function test_the_public_allowlist_has_no_stale_entries(): void
    {
        $existing = array_column($this->routes(), 0);

        foreach (self::PUBLIC as $key) {
            $this->assertContains($key, $existing, "allowlisted route {$key} no longer exists — remove it from the list");
        }
    }

    public function test_routes_with_record_parameters_are_never_public(): void
    {
        foreach ($this->routes() as [$key, $route]) {
            if (preg_match('/\{(ticket|comment|attachment|assignment|user)\}/', $route->uri())) {
                $this->assertNotContains($key, self::PUBLIC);
                $middleware = $route->gatherMiddleware();
                $this->assertTrue(in_array('auth', $middleware, true) || in_array('auth:sanctum', $middleware, true), "{$key} must require auth");
            }
        }
    }

    public function test_admin_routes_require_the_admin_role(): void
    {
        $seen = 0;

        foreach ($this->routes() as [$key, $route]) {
            if (str_starts_with($route->uri(), 'admin/')) {
                $this->assertContains('role:admin', $route->gatherMiddleware(), "{$key} must require role:admin");
                $seen++;
            }
        }

        $this->assertGreaterThan(0, $seen);
    }

    public function test_guest_only_routes_use_the_guest_middleware(): void
    {
        foreach (['GET login', 'POST login', 'GET forgot-password', 'POST forgot-password', 'GET reset-password/{token}', 'POST reset-password'] as $wanted) {
            foreach ($this->routes() as [$key, $route]) {
                if ($key === $wanted) {
                    $this->assertContains('guest', $route->gatherMiddleware(), $key);
                }
            }
        }
    }

    public function test_sensitive_post_routes_are_throttled(): void
    {
        foreach ($this->routes() as [$key, $route]) {
            if (in_array($key, ['POST forgot-password', 'POST reset-password', 'POST api/auth/login'], true)) {
                $this->assertTrue(
                    collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:')),
                    "{$key} must be rate limited",
                );
            }
        }
        // POST /login is rate limited inside LoginRequest (5 attempts per email+IP) — see AuthenticationTest.
    }

    public function test_the_expected_security_routes_exist_and_dangerous_ones_do_not(): void
    {
        $keys = array_column($this->routes(), 0);

        foreach (['POST login', 'POST logout', 'POST forgot-password', 'POST reset-password', 'GET tickets/{ticket}', 'POST api/auth/login', 'GET api/tickets'] as $needed) {
            $this->assertContains($needed, $keys);
        }

        // No self-registration, email-verification (signed URLs), confirm-password or account-deletion routes.
        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('#register|verify-email|confirm-password|DELETE profile|storage/#', $key, "unexpected route {$key}");
        }
    }

    public function test_the_web_group_checks_sessions_against_password_changes(): void
    {
        $router = app('router');

        // authenticateSessions() appends the `auth.session` alias to the web group.
        $this->assertContains('auth.session', $router->getMiddlewareGroups()['web']);
        $this->assertSame(AuthenticateSession::class, $router->getMiddleware()['auth.session']);
    }

    public function test_ticket_routes_have_scoped_bindings_for_nested_resources(): void
    {
        foreach ($this->routes() as [$key, $route]) {
            if (preg_match('#^GET (api/)?tickets/\{ticket\}/(comments/\{comment\}|attachments/\{attachment\})$#', $key)) {
                $this->assertTrue($route->enforcesScopedBindings(), "{$key} must use scoped bindings");
            }
        }
    }
}
