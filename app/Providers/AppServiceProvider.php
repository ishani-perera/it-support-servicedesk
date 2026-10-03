<?php

namespace App\Providers;

use App\Services\NotificationAccessService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as ViewContract;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by('api:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('api-write', fn (Request $request) => Limit::perMinute(20)
            ->by('api-write:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // N+1 guard: lazily loading a relationship on a model that came from a
        // multi-row query throws outside production, so missing eager loads
        // (with()) are caught in development and CI instead of reaching users.
        Model::preventLazyLoading(! $this->app->isProduction());

        // One password policy for every place a password is chosen (reset,
        // change-password, future admin user creation). The breached-password
        // check calls an external API, so it is enabled in production only.
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(12)->mixedCase()->numbers()->uncompromised()
            : Password::min(12)->mixedCase()->numbers());

        View::composer('layouts.app', function (ViewContract $view): void {
            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $access = app(NotificationAccessService::class);
            $visible = $access->visibleTo($user);
            $view->with([
                'recentNotifications' => $visible->latest()->limit(5)->get(),
                'unreadNotificationCount' => $access->visibleTo($user)->whereNull('read_at')->count(),
            ]);
        });
    }
}
