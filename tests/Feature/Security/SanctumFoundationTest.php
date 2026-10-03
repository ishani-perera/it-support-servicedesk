<?php

namespace Tests\Feature\Security;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * These tests verify the Sanctum foundation remains intact as the REST API
 * adds application routes. Throw-away routes continue to prove that tokens
 * never bypass Policies.
 */
class SanctumFoundationTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();

        Route::middleware(['api', 'auth:sanctum', 'active'])->prefix('api/_t')->group(function () {
            Route::get('me', fn (Request $request) => ['id' => $request->user()->id]);

            Route::get('tickets/{ticket}', function (Ticket $ticket) {
                Gate::authorize('view', $ticket);

                return ['id' => $ticket->id];
            });

            Route::get('admin-only', fn () => ['ok' => true])->middleware('role:admin');
        });
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function asApiUser(User $user): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->tokenFor($user));
    }

    public function test_sanctum_is_installed_and_wired_to_the_user_model(): void
    {
        $this->assertContains(HasApiTokens::class, class_uses_recursive(User::class));
        $this->assertTrue(class_exists(PersonalAccessToken::class));
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        $this->assertSame(['web'], config('sanctum.guard'));
        $this->assertNotNull(config('auth.guards.sanctum') ?? app('auth')->guard('sanctum'));
    }

    public function test_stateful_domains_contain_no_wildcards(): void
    {
        foreach (config('sanctum.stateful') as $domain) {
            $this->assertStringNotContainsString('*', $domain);
        }
    }

    public function test_unauthenticated_api_requests_get_json_401_not_a_redirect(): void
    {
        // Deliberately NO Accept header: /api/* must still answer JSON.
        $response = $this->get('/api/_t/me');

        $response->assertUnauthorized();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertExactJson(['error' => ['code' => 'unauthenticated', 'message' => 'Unauthenticated.']]);
        $this->assertNull($response->headers->get('Location'));
    }

    public function test_an_invalid_or_malformed_token_is_rejected(): void
    {
        $this->withToken('1|not-a-real-token')->getJson('/api/_t/me')->assertUnauthorized();
        $this->withToken('garbage')->getJson('/api/_t/me')->assertUnauthorized();
        $this->withHeader('Authorization', 'Basic '.base64_encode('a:b'))->getJson('/api/_t/me')->assertUnauthorized();
    }

    public function test_a_valid_token_authenticates(): void
    {
        $this->asApiUser($this->employeeA)->getJson('/api/_t/me')->assertOk()->assertJson(['id' => $this->employeeA->id]);
    }

    public function test_tokens_are_stored_hashed(): void
    {
        $plain = $this->tokenFor($this->employeeA);
        [, $secret] = explode('|', $plain, 2);

        $stored = DB::table('personal_access_tokens')->where('tokenable_id', $this->employeeA->id)->value('token');

        $this->assertNotSame($secret, $stored);
        $this->assertSame(hash('sha256', $secret), $stored);
    }

    public function test_a_revoked_token_stops_working(): void
    {
        $plain = $this->tokenFor($this->employeeA);
        $this->withToken($plain)->getJson('/api/_t/me')->assertOk();

        $this->employeeA->tokens()->delete();
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->getJson('/api/_t/me')->assertUnauthorized();
    }

    public function test_a_soft_deleted_user_cannot_use_an_existing_token(): void
    {
        $plain = $this->tokenFor($this->employeeA);
        $this->employeeA->delete();
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->getJson('/api/_t/me')->assertUnauthorized();
    }

    public function test_a_deactivated_user_cannot_use_an_existing_token(): void
    {
        $plain = $this->tokenFor($this->employeeA);
        $this->employeeA->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();

        $this->withToken($plain)->getJson('/api/_t/me')->assertForbidden();
    }

    public function test_the_active_check_runs_before_route_model_binding_on_the_api(): void
    {
        $plain = $this->tokenFor($this->employeeA);
        $this->employeeA->forceFill(['is_active' => false])->save();
        $this->app['auth']->forgetGuards();

        // 403 for an existing AND a non-existing id: nothing to probe.
        $this->withToken($plain)->getJson('/api/_t/tickets/'.$this->ticketA->id)->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($plain)->getJson('/api/_t/tickets/999999')->assertForbidden();
    }

    public function test_api_authentication_does_not_bypass_policies(): void
    {
        $this->asApiUser($this->employeeA)->getJson('/api/_t/tickets/'.$this->ticketA->id)->assertOk();
        $this->asApiUser($this->employeeA)->getJson('/api/_t/tickets/'.$this->ticketB->id)->assertForbidden();

        $this->asApiUser($this->supportOne)->getJson('/api/_t/tickets/'.$this->ticketA->id)->assertOk();
        $this->asApiUser($this->supportOne)->getJson('/api/_t/tickets/'.$this->ticketB->id)->assertOk();

        $this->asApiUser($this->admin)->getJson('/api/_t/tickets/'.$this->ticketB->id)->assertOk();
    }

    public function test_the_role_middleware_works_with_token_authentication(): void
    {
        $this->asApiUser($this->employeeA)->getJson('/api/_t/admin-only')->assertForbidden();
        $this->asApiUser($this->supportOne)->getJson('/api/_t/admin-only')->assertForbidden();
        $this->asApiUser($this->admin)->getJson('/api/_t/admin-only')->assertOk();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/_t/admin-only')->assertUnauthorized();
    }

    public function test_routes_api_exposes_the_phase_ten_endpoints(): void
    {
        $apiUris = collect(Route::getRoutes())->pluck('uri')->filter(fn ($uri) => str_starts_with($uri, 'api/'));
        $this->assertContains('api/auth/login', $apiUris);
        $this->assertContains('api/auth/user', $apiUris);
        $this->assertContains('api/tickets', $apiUris);
        $this->assertContains('api/notifications', $apiUris);
    }
}
