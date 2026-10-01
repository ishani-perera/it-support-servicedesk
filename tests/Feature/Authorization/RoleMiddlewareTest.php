<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();

        // Throw-away routes that exercise the middleware in isolation.
        Route::middleware(['web', 'auth', 'active', 'role:employee'])->get('/_t/employee', fn () => 'ok');
        Route::middleware(['web', 'auth', 'active', 'role:support'])->get('/_t/support', fn () => 'ok');
        Route::middleware(['web', 'auth', 'active', 'role:admin'])->get('/_t/admin', fn () => 'ok');
        Route::middleware(['web', 'auth', 'active', 'role:support,admin'])->get('/_t/staff', fn () => 'ok');
        Route::middleware(['web', 'auth', 'active', 'role:support, admin'])->get('/_t/staff-spaced', fn () => 'ok');
        Route::middleware(['web', 'role:admin'])->get('/_t/role-only-admin', fn () => 'ok'); // no explicit auth
        Route::middleware(['web', 'auth', 'role:suport'])->get('/_t/typo', fn () => 'ok');
        Route::middleware(['web', 'auth', 'role'])->get('/_t/no-role', fn () => 'ok');
    }

    /** @return array<string, array{0: string, 1: list<string>}> */
    public static function matrix(): array
    {
        return [
            'employee-only' => ['/_t/employee', ['employeeA']],
            'support-only' => ['/_t/support', ['supportOne']],
            'admin-only' => ['/_t/admin', ['admin']],
            'support-or-admin' => ['/_t/staff', ['supportOne', 'admin']],
            'support-or-admin (spaces)' => ['/_t/staff-spaced', ['supportOne', 'admin']],
        ];
    }

    /**
     * @param  list<string>  $allowed
     */
    #[DataProvider('matrix')]
    public function test_only_the_listed_roles_get_through(string $uri, array $allowed): void
    {
        foreach (['employeeA', 'supportOne', 'admin'] as $who) {
            $response = $this->actingAs($this->{$who})->get($uri);

            in_array($who, $allowed, true)
                ? $response->assertOk()
                : $response->assertForbidden();

            $this->app['auth']->forgetGuards();
        }
    }

    public function test_employee_cannot_use_support_only_functionality(): void
    {
        $this->actingAs($this->employeeA)->get('/_t/support')->assertForbidden();
    }

    public function test_employee_cannot_use_admin_functionality(): void
    {
        $this->actingAs($this->employeeA)->get('/_t/admin')->assertForbidden();
        $this->actingAs($this->employeeA)->get('/admin/users')->assertForbidden();
    }

    public function test_support_cannot_use_admin_functionality(): void
    {
        $this->actingAs($this->supportOne)->get('/_t/admin')->assertForbidden();
        $this->actingAs($this->supportOne)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_can_use_admin_functionality(): void
    {
        $this->actingAs($this->admin)->get('/_t/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin/users')->assertOk();
    }

    public function test_there_is_no_implicit_role_hierarchy(): void
    {
        // Admin is NOT silently allowed through role:support / role:employee.
        $this->actingAs($this->admin)->get('/_t/support')->assertForbidden();
        $this->actingAs($this->admin)->get('/_t/employee')->assertForbidden();
    }

    public function test_guests_are_redirected_to_login_or_get_json_401(): void
    {
        $this->get('/_t/admin')->assertRedirect('/login');
        $this->get('/_t/role-only-admin')->assertRedirect('/login'); // role alone also authenticates
        $this->getJson('/_t/admin')->assertUnauthorized();
        $this->getJson('/_t/role-only-admin')->assertUnauthorized();
    }

    public function test_a_deactivated_user_is_forbidden_by_the_role_middleware_itself(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        // Reaches the role middleware without the `active` alias in front of it.
        $this->actingAs($inactiveAdmin)->get('/_t/role-only-admin')->assertForbidden();
    }

    public function test_an_unknown_role_name_fails_loudly_instead_of_silently(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown role [suport]');

        $this->actingAs($this->admin)->get('/_t/typo');
    }

    public function test_the_role_middleware_requires_at_least_one_role(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(InvalidArgumentException::class);

        $this->actingAs($this->admin)->get('/_t/no-role');
    }

    public function test_the_middleware_aliases_are_registered(): void
    {
        $aliases = app('router')->getMiddleware();

        $this->assertSame(EnsureUserHasRole::class, $aliases['role']);
        $this->assertSame(EnsureUserIsActive::class, $aliases['active']);
    }

    public function test_role_names_come_only_from_the_enum(): void
    {
        $this->assertSame(['employee', 'support', 'admin'], UserRole::values());

        // No source file outside the enum / seeders / factories / tests hard-codes a role string comparison.
        foreach (['app/Http', 'app/Policies', 'app/Models'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir))) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $this->assertDoesNotMatchRegularExpression(
                    "/['\"](employee|support|admin)['\"]\s*(===?|!==?)|(===?|!==?)\s*['\"](employee|support|admin)['\"]/",
                    file_get_contents($file->getPathname()),
                    $file->getPathname().' hard-codes a role string',
                );
            }
        }
    }
}
