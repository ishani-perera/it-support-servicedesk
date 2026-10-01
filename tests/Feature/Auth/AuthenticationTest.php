<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private const PASSWORD = 'Correct-Horse-9-Battery';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    private function userWithPassword(UserRole $role = UserRole::Employee, array $attributes = []): User
    {
        return User::factory()->role($role)->create(array_merge(['password' => self::PASSWORD], $attributes));
    }

    /* ----- login ----- */

    public function test_the_login_page_renders_for_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_each_role_can_log_in(): void
    {
        foreach ([UserRole::Employee, UserRole::Support, UserRole::Admin] as $role) {
            $user = $this->userWithPassword($role);

            $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
                ->assertRedirect('/home');

            $this->assertAuthenticatedAs($user);

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_the_landing_page_shows_the_signed_in_user_and_role(): void
    {
        $user = $this->userWithPassword(UserRole::Support);

        $this->actingAs($user)->get('/home')
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('IT Support');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = $this->userWithPassword();

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_an_unknown_email_gets_the_same_error_as_a_wrong_password(): void
    {
        $user = $this->userWithPassword();

        $this->post('/login', ['email' => $user->email, 'password' => 'nope'])->assertSessionHasErrors('email');
        $wrongPassword = session('errors')->get('email');

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'nope'])->assertSessionHasErrors('email');
        $unknownEmail = session('errors')->get('email');

        $this->assertSame($wrongPassword, $unknownEmail);
        $this->assertSame([trans('auth.failed')], $unknownEmail);
        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_log_in_and_gets_the_generic_error(): void
    {
        $user = $this->userWithPassword(attributes: ['is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_a_soft_deleted_account_cannot_log_in(): void
    {
        $user = $this->userWithPassword();
        $user->delete();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_input_is_validated(): void
    {
        $this->post('/login', ['email' => 'not-an-email', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        $user = $this->userWithPassword();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'.$i]);
        }

        // Even the CORRECT password is refused while locked out.
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_a_successful_login_clears_the_failure_counter(): void
    {
        $user = $this->userWithPassword();

        foreach (range(1, 3) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'.$i]);
        }

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/home');
        $this->post('/logout');

        foreach (range(1, 4) as $i) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'.$i]);
        }
        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])->assertRedirect('/home');
    }

    public function test_remember_me_issues_a_recaller_cookie_only_when_requested(): void
    {
        $user = $this->userWithPassword();

        $without = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertNull($this->recallerCookie($without));
        $this->post('/logout');

        $with = $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD, 'remember' => '1']);
        $this->assertNotNull($this->recallerCookie($with));
        $this->assertNotEmpty($user->fresh()->remember_token);
    }

    private function recallerCookie($response): mixed
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (str_starts_with($cookie->getName(), 'remember_web_')) {
                return $cookie;
            }
        }

        return null;
    }

    public function test_the_intended_url_is_honoured_after_login(): void
    {
        $user = $this->userWithPassword();

        $this->get('/tickets/'.$this->ticketA->id)->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('/tickets/'.$this->ticketA->id);
    }

    public function test_a_signed_in_user_is_redirected_away_from_the_login_page(): void
    {
        $this->actingAs($this->employeeA)->get('/login')->assertRedirect('/home');
    }

    /* ----- logout ----- */

    public function test_logout_ends_the_session(): void
    {
        $user = $this->userWithPassword();

        $this->post('/login', ['email' => $user->email, 'password' => self::PASSWORD]);
        $this->assertAuthenticatedAs($user);
        $this->get('/home')->assertOk();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->get('/home')->assertRedirect('/login');
    }

    public function test_logout_requires_authentication_and_post(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($this->employeeA)->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();
    }

    /* ----- protected pages ----- */

    public function test_guests_cannot_reach_protected_pages(): void
    {
        $ticket = $this->ticketA;

        $this->get('/home')->assertRedirect('/login');
        $this->get('/tickets/'.$ticket->id)->assertRedirect('/login');
        $this->get("/tickets/{$ticket->id}/comments/{$this->publicA->id}")->assertRedirect('/login');
        $this->get("/tickets/{$ticket->id}/attachments/{$this->attA->id}")->assertRedirect('/login');
        $this->get('/admin/users')->assertRedirect('/login');
        $this->patch('/admin/users/'.$this->employeeA->id, ['role' => 'admin'])->assertRedirect('/login');
        $this->patch('/profile', ['name' => 'x'])->assertRedirect('/login');
        $this->put('/password', [])->assertRedirect('/login');
    }

    public function test_guests_receive_json_401_when_they_ask_for_json(): void
    {
        $this->getJson('/tickets/'.$this->ticketA->id)->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
        $this->getJson('/admin/users')->assertUnauthorized();
    }

    public function test_an_account_deactivated_while_signed_in_is_signed_out_on_the_next_request(): void
    {
        $this->actingAs($this->employeeA)->get('/home')->assertOk();

        $this->employeeA->forceFill(['is_active' => false])->save();

        $this->get('/home')->assertRedirect('/login');
        $this->assertGuest();
    }

    /* ----- real demo data ----- */

    public function test_the_documented_demo_accounts_can_log_in_through_the_real_form(): void
    {
        $this->seed(DemoUserSeeder::class);

        foreach (['admin@example.com', 'support1@example.com', 'employee1@example.com'] as $email) {
            $this->post('/login', ['email' => $email, 'password' => DemoUserSeeder::DEV_PASSWORD])
                ->assertRedirect('/home');
            $this->assertAuthenticated();
            $this->post('/logout');
        }
    }
}
