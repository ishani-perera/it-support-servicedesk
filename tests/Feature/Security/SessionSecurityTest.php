<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private const PASSWORD = 'Session-Test-Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    private function loginPayload(User $user): array
    {
        return ['email' => $user->email, 'password' => self::PASSWORD];
    }

    private function userWithPassword(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }

    /* ----- session id lifecycle ----- */

    public function test_the_session_id_is_regenerated_on_login(): void
    {
        $user = $this->userWithPassword();

        $this->startSession();
        $before = session()->getId();

        $this->post('/login', $this->loginPayload($user))->assertRedirect('/home');

        $this->assertNotSame($before, session()->getId(), 'session id must change on login');
    }

    public function test_a_failed_login_does_not_authenticate_or_rotate_into_a_logged_in_session(): void
    {
        $user = $this->userWithPassword();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $this->assertGuest();
        $this->assertNull(session()->get('login_web_'.sha1(SessionGuard::class)));
    }

    public function test_logout_destroys_the_session_data_and_rotates_session_id_and_csrf_token(): void
    {
        $user = $this->userWithPassword();
        $this->post('/login', $this->loginPayload($user));
        session()->put('sensitive', 'value');

        $idBefore = session()->getId();
        $csrfBefore = session()->token();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotSame($idBefore, session()->getId());
        $this->assertNotSame($csrfBefore, session()->token());
        $this->assertNull(session('sensitive'), 'session data must be flushed');
        $this->assertSame([], array_values(array_filter(array_keys(session()->all()), fn ($k) => str_starts_with($k, 'login_'))));
    }

    /**
     * End-to-end fixation test against the real database session driver:
     * a session id the attacker knows BEFORE login must be dead after login,
     * and a session id from BEFORE logout must be dead after logout.
     */
    public function test_a_pre_login_session_id_cannot_be_reused_after_login_or_logout(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->userWithPassword();
        $cookieName = config('session.cookie');

        // 1. The attacker obtains / plants a session id by visiting the login page.
        $visit = $this->get('/login');
        $planted = $visit->getCookie($cookieName, decrypt: false)->getValue();
        $plantedId = $this->decryptSessionCookie($planted);
        $this->assertDatabaseHas('sessions', ['id' => $plantedId]);

        // 2. The victim logs in using that same session.
        $login = $this->withUnencryptedCookie($cookieName, $planted)->post('/login', $this->loginPayload($user));
        $login->assertRedirect('/home');
        $newCookie = $login->getCookie($cookieName, decrypt: false)->getValue();
        $newId = $this->decryptSessionCookie($newCookie);

        $this->assertNotSame($plantedId, $newId, 'a new session id is issued at login');
        $this->assertDatabaseMissing('sessions', ['id' => $plantedId]);
        $this->assertDatabaseHas('sessions', ['id' => $newId, 'user_id' => $user->id]);

        // 3. The attacker replays the planted id: still a guest.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withUnencryptedCookie($cookieName, $planted)->get('/home')->assertRedirect('/login');

        // 4. Logging out kills the server-side session row.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $logout = $this->withUnencryptedCookie($cookieName, $newCookie)->post('/logout');
        $logout->assertRedirect('/');
        $this->assertDatabaseMissing('sessions', ['id' => $newId]);

        // 5. Replaying the old authenticated cookie no longer works.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookie($cookieName, $newCookie)->get('/home')->assertRedirect('/login');
    }

    private function decryptSessionCookie(string $encrypted): string
    {
        return CookieValuePrefix::remove(app('encrypter')->decrypt($encrypted, false));
    }

    public function test_session_identifiers_never_appear_in_urls(): void
    {
        $user = $this->userWithPassword();

        $response = $this->post('/login', $this->loginPayload($user));
        $location = (string) $response->headers->get('Location');

        $this->assertStringNotContainsString(session()->getId(), $location);
        $this->assertStringNotContainsString(config('session.cookie'), $location);
        $this->assertStringNotContainsString('?', $location);

        // A session id passed in the URL is ignored.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get('/home?'.config('session.cookie').'='.session()->getId())->assertRedirect('/login');
    }

    /* ----- cookie flags / config ----- */

    public function test_the_session_cookie_is_http_only_and_same_site_and_not_weakened(): void
    {
        $this->assertTrue(config('session.http_only'), 'session cookie must be HttpOnly');
        $this->assertContains(config('session.same_site'), ['lax', 'strict']);
        $this->assertSame('/', config('session.path'));

        $response = $this->get('/login');
        $cookie = $response->getCookie(config('session.cookie'), decrypt: false);

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_session_cookie_secure_flag_is_driven_by_configuration(): void
    {
        config(['session.secure' => true]);

        $cookie = $this->get('/login')->getCookie(config('session.cookie'), decrypt: false);

        $this->assertTrue($cookie->isSecure());
    }

    public function test_sessions_are_stored_server_side_by_default(): void
    {
        // Session DATA lives in the database, not in the cookie (the cookie only carries an opaque, encrypted id).
        $this->assertStringContainsString('SESSION_DRIVER=database', file_get_contents(base_path('.env.example')));
        $this->assertStringContainsString("env('SESSION_DRIVER', 'database')", file_get_contents(config_path('session.php')));
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    /* ----- CSRF ----- */

    public function test_every_state_changing_web_route_is_covered_by_csrf_protection(): void
    {
        $router = app('router');
        $checked = [];

        foreach (Route::getRoutes() as $route) {
            $unsafe = array_diff($route->methods(), ['GET', 'HEAD', 'OPTIONS']);

            if ($unsafe === [] || str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $this->assertContains(ValidateCsrfToken::class, $router->gatherRouteMiddleware($route), 'no CSRF check on '.$route->uri());
            $checked[] = $route->uri();
        }

        foreach (['login', 'logout', 'forgot-password', 'reset-password', 'profile', 'password', 'admin/users/{user}'] as $uri) {
            $this->assertContains($uri, $checked, "{$uri} should be a CSRF-checked state-changing route");
        }
    }

    public function test_no_route_is_exempt_from_csrf_verification(): void
    {
        $middleware = new class(app(), app('encrypter')) extends ValidateCsrfToken
        {
            public function exceptList(): array
            {
                return $this->except;
            }
        };

        $this->assertSame([], $middleware->exceptList(), 'the CSRF except-list must stay empty');
    }

    public function test_a_post_without_a_valid_csrf_token_is_rejected_with_419(): void
    {
        // Laravel skips CSRF while running tests; force the real check.
        $strict = new class(app(), app('encrypter')) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        };

        $request = fn (array $input = [], ?string $header = null) => tap(
            Request::create('/logout', 'POST', $input),
            function (Request $r) use ($header) {
                $r->setLaravelSession(app('session')->driver());
                if ($header !== null) {
                    $r->headers->set('X-CSRF-TOKEN', $header);
                }
            }
        );

        $ok = fn () => response('passed');

        // no token
        try {
            $strict->handle($request(), $ok);
            $this->fail('A POST without a CSRF token must be rejected.');
        } catch (TokenMismatchException $e) {
            $this->assertSame('CSRF token mismatch.', $e->getMessage());
        }

        // wrong token
        try {
            $strict->handle($request(['_token' => 'forged']), $ok);
            $this->fail('A forged CSRF token must be rejected.');
        } catch (TokenMismatchException) {
            $this->addToAssertionCount(1);
        }

        // right token (form field and header)
        $session = app('session')->driver();
        $session->start();
        $good = $session->token();

        $this->assertSame('passed', $strict->handle($request(['_token' => $good]), $ok)->getContent());
        $this->assertSame('passed', $strict->handle($request([], $good), $ok)->getContent());
    }

    public function test_every_html_form_carries_a_csrf_token(): void
    {
        $views = [
            'login' => $this->get('/login')->getContent(),
            'forgot' => $this->get('/forgot-password')->getContent(),
            'reset' => $this->get('/reset-password/tok')->getContent(),
        ];

        foreach ($views as $name => $html) {
            $this->assertStringContainsString('name="_token"', $html, "{$name} form lacks @csrf");
        }

        $this->actingAs($this->employeeA);
        $this->assertStringContainsString('name="_token"', $this->get('/home')->getContent());
    }

    public function test_authenticated_pages_are_sent_with_private_no_cache_headers(): void
    {
        $response = $this->actingAs($this->employeeA)->get('/home');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }
}
