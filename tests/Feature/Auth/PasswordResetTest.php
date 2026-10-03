<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\EmailField;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private const OLD = 'Old-Password-123x';

    private const NEW = 'Brand-New-Passw0rd!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    private function user(array $attributes = []): User
    {
        return User::factory()->create(array_merge(['password' => self::OLD], $attributes));
    }

    /** Requests a reset link and returns the plaintext token that was e-mailed. */
    private function requestToken(User $user): string
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return (string) $token;
    }

    public function test_the_forgot_and_reset_pages_render(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->get('/reset-password/some-token?email=a@example.com')->assertOk()->assertSee('some-token');
    }

    public function test_a_reset_link_is_sent_to_an_active_account(): void
    {
        $user = $this->user();

        $token = $this->requestToken($user);

        $this->assertNotSame('', $token);
        // Only a HASH of the token is stored, never the token itself.
        $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
        $this->assertNotSame($token, $stored);
        $this->assertTrue(Hash::check($token, $stored));
    }

    public function test_the_response_does_not_reveal_whether_an_account_exists(): void
    {
        Notification::fake();
        $user = $this->user();

        $known = $this->post('/forgot-password', ['email' => $user->email]);
        $knownStatus = session('status');
        $unknown = $this->post('/forgot-password', ['email' => 'nobody-here@example.com']);
        $unknownStatus = session('status');

        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        $this->assertSame($knownStatus, $unknownStatus);
        $this->assertSame(0, \count(session('errors')?->all() ?? []));
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        Notification::assertCount(1);
    }

    public function test_the_broker_throttle_does_not_leak_account_existence_either(): void
    {
        Notification::fake();
        $user = $this->user();

        $first = $this->post('/forgot-password', ['email' => $user->email]);
        $firstStatus = session('status');
        $second = $this->post('/forgot-password', ['email' => $user->email]); // throttled by the broker
        $secondStatus = session('status');

        $this->assertSame($firstStatus, $secondStatus);
        $this->assertSame($first->getStatusCode(), $second->getStatusCode());
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_deactivated_accounts_never_receive_a_reset_link(): void
    {
        Notification::fake();
        $user = $this->user(['is_active' => false]);

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_header_injection_attempts_in_the_email_field_are_rejected(): void
    {
        Notification::fake();
        $user = $this->user();

        foreach ([
            $user->email."\nBcc: attacker@example.net",
            $user->email."\r\nBcc: attacker@example.net",
            "bad\0@example.com",
        ] as $payload) {
            $this->post('/forgot-password', ['email' => $payload])->assertSessionHasErrors('email');
        }

        Notification::assertNothingSent();
    }

    public function test_the_email_rule_itself_rejects_a_raw_trailing_newline(): void
    {
        // TrimStrings removes a trailing newline from HTTP input before validation,
        // so the rule is also exercised directly (defence in depth).
        foreach (["a@example.com\n", "a@example.com\r", "a@example.com\nBcc: x@example.net"] as $raw) {
            $this->assertTrue(
                validator(['email' => $raw], ['email' => EmailField::rules()])->fails(),
                'accepted: '.json_encode($raw)
            );
        }

        $this->assertFalse(validator(['email' => 'valid.person@example.com'], ['email' => EmailField::rules()])->fails());
    }

    public function test_a_trailing_newline_is_trimmed_and_the_link_goes_to_the_clean_address_only(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post('/forgot-password', ['email' => $user->email."\n"]);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_a_valid_token_resets_the_password_and_it_is_stored_hashed(): void
    {
        $user = $this->user();
        $user->createToken('existing-device');
        $oldHash = $user->password;
        $oldRemember = $user->remember_token;
        $token = $this->requestToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotSame($oldHash, $user->password);
        $this->assertNotSame(self::NEW, $user->password);
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertFalse(Hash::check(self::OLD, $user->password));
        $this->assertNotSame($oldRemember, $user->remember_token, 'remember-me token must be rotated');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertSame(0, $user->tokens()->count(), 'password reset must revoke existing API tokens');
        // No auto-login from an e-mailed link.
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => self::NEW])->assertRedirect('/home');
    }

    public function test_a_reset_token_is_single_use(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW];

        $this->post('/reset-password', $payload)->assertRedirect('/login');

        $again = array_merge($payload, ['password' => 'Another-Passw0rd-2', 'password_confirmation' => 'Another-Passw0rd-2']);
        $this->post('/reset-password', $again)->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
    }

    public function test_a_reset_token_expires(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);

        $this->assertSame(60, config('auth.passwords.users.expire'));
        $this->travel(61)->minutes();

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_a_token_cannot_be_used_to_reset_another_users_password(): void
    {
        $alice = $this->user();
        $victim = $this->user();
        $aliceToken = $this->requestToken($alice);

        $this->post('/reset-password', [
            'token' => $aliceToken,
            'email' => $victim->email,
            'password' => self::NEW,
            'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD, $victim->fresh()->password), 'victim password must be untouched');
        $this->assertTrue(Hash::check(self::OLD, $alice->fresh()->password));
    }

    public function test_a_forged_token_is_rejected(): void
    {
        $user = $this->user();
        $this->requestToken($user);

        $this->post('/reset-password', [
            'token' => str_repeat('a', 64), 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_an_account_deactivated_after_requesting_a_link_cannot_use_it(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);
        $user->forceFill(['is_active' => false])->save();

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_failure_messages_do_not_distinguish_the_cause(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);
        $body = ['password' => self::NEW, 'password_confirmation' => self::NEW];

        $this->post('/reset-password', $body + ['token' => 'bad', 'email' => $user->email]);
        $badToken = session('errors')->first('email');
        $this->post('/reset-password', $body + ['token' => $token, 'email' => 'ghost@example.com']);
        $unknownUser = session('errors')->first('email');

        $this->assertSame($badToken, $unknownUser);
    }

    public function test_the_new_password_must_meet_the_password_policy(): void
    {
        $user = $this->user();
        $token = $this->requestToken($user);

        foreach (['short1A', 'alllowercase1234', 'NoNumbersHereAtAll'] as $weak) {
            $this->post('/reset-password', [
                'token' => $token, 'email' => $user->email, 'password' => $weak, 'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password');
        }

        $this->post('/reset-password', [
            'token' => $token, 'email' => $user->email, 'password' => self::NEW, 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));
    }

    public function test_a_password_reset_signs_out_existing_sessions(): void
    {
        $user = $this->user();

        $this->post('/login', ['email' => $user->email, 'password' => self::OLD]);
        $this->get('/home')->assertOk();

        // What the reset controller does to the account:
        $user->refresh()->forceFill(['password' => self::NEW])->save();

        // Real requests each start a fresh process; in tests the guard would
        // still hold the user object cached from the previous request.
        $this->app['auth']->forgetGuards();

        $this->get('/home')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_changing_your_own_password_keeps_this_session_but_needs_the_current_password(): void
    {
        $user = $this->user();
        $this->post('/login', ['email' => $user->email, 'password' => self::OLD]);

        $this->put('/password', [
            'current_password' => 'wrong', 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check(self::OLD, $user->fresh()->password));

        $this->put('/password', [
            'current_password' => self::OLD, 'password' => self::NEW, 'password_confirmation' => self::NEW,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check(self::NEW, $user->fresh()->password));
        $this->get('/home')->assertOk(); // this device stays signed in
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $user = $this->user();
        $this->actingAs($user)->put('/password', [
            'current_password' => self::OLD, 'password' => self::OLD, 'password_confirmation' => self::OLD,
        ])->assertSessionHasErrors('password');
    }
}
