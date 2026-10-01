<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }
}
