<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class PasswordSecurityTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    public function test_passwords_are_hashed_never_stored_in_plaintext(): void
    {
        $plain = 'Plain-Text-Secret-77';
        $user = User::create(['name' => 'Hash Check', 'email' => 'hash.check@example.com', 'password' => $plain]);

        $stored = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame($plain, $stored);
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check($plain, $stored));
        $this->assertFalse(Hash::needsRehash($stored));
    }

    public function test_the_plaintext_appears_in_no_column_of_the_users_row(): void
    {
        $plain = 'Another-Secret-4242';
        $user = User::factory()->create(['password' => $plain]);

        $row = (array) DB::table('users')->where('id', $user->id)->first();

        foreach ($row as $column => $value) {
            $this->assertStringNotContainsString($plain, (string) $value, "plaintext leaked into users.{$column}");
        }
    }

    public function test_updating_a_password_rehashes_it(): void
    {
        $user = User::factory()->create(['password' => 'First-Passw0rd-xx']);
        $first = DB::table('users')->where('id', $user->id)->value('password');

        $user->update(['password' => 'Second-Passw0rd-yy']);
        $second = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotSame($first, $second);
        $this->assertStringStartsWith('$2y$', $second);
        $this->assertTrue(Hash::check('Second-Passw0rd-yy', $second));
    }

    public function test_changing_a_password_revokes_all_sanctum_tokens(): void
    {
        $user = User::factory()->create(['password' => 'First-Passw0rd-xx']);
        $user->createToken('first');
        $user->createToken('second');

        $this->actingAs($user)->putJson('/password', [
            'current_password' => 'First-Passw0rd-xx',
            'password' => 'Second-Passw0rd-yy',
            'password_confirmation' => 'Second-Passw0rd-yy',
        ])->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_password_and_remember_token_are_never_serialised(): void
    {
        $user = User::factory()->create();

        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertStringNotContainsString('password', $user->toJson());
    }

    public function test_the_login_password_is_not_kept_in_the_session(): void
    {
        $user = User::factory()->create(['password' => 'Session-Check-Passw0rd']);

        $this->post('/login', ['email' => $user->email, 'password' => 'Session-Check-Passw0rd']);

        $this->assertStringNotContainsString('Session-Check-Passw0rd', json_encode(session()->all()));
    }

    public function test_the_seeded_demo_passwords_are_stored_as_bcrypt_hashes(): void
    {
        $this->seed(DemoUserSeeder::class);

        $emails = ['admin@example.com', 'support1@example.com', 'support2@example.com', 'support3@example.com'];
        foreach (range(1, 8) as $i) {
            $emails[] = "employee{$i}@example.com";
        }

        $hashes = DB::table('users')->whereIn('email', $emails)->pluck('password', 'email');
        $this->assertCount(12, $hashes);

        foreach ($hashes as $email => $hash) {
            $this->assertStringStartsWith('$2y$', $hash, $email);
            $this->assertNotSame(DemoUserSeeder::DEV_PASSWORD, $hash, $email);
            $this->assertTrue(Hash::check(DemoUserSeeder::DEV_PASSWORD, $hash), $email);
        }
    }

    public function test_the_password_policy_is_the_configured_default(): void
    {
        $rule = Password::default();
        $validator = validator(['p' => 'short'], ['p' => $rule]);
        $this->assertTrue($validator->fails());

        $this->assertFalse(validator(['p' => 'Long-Enough-Passw0rd'], ['p' => $rule])->fails());
    }

    public function test_the_password_reset_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('password_reset_tokens'));
    }
}
