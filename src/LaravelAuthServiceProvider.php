<?php

namespace Markt\LaravelAuth;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class LaravelAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravel-auth.php',
            'laravel-auth'
        );
    }

    public function boot(): void
    {
        //variable configs
        $this->publishes([
            __DIR__ . '/../config/laravel-auth.php' => config_path('laravel-auth.php'),
        ], 'laravel-auth-config');

        //database migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laravel-auth-migrations');

        //api routes
        $this->loadRoutesFrom(
            __DIR__ . '/routes/api.php'
        );

        //prefix the routes
        Route::prefix(config('laravel-auth.routes.prefix'))
            ->group(
                __DIR__ . '/routes/api.php'
            );

        //login rate limiter
        RateLimiter::for('laravel-auth-login', function ($request) {
            return Limit::perMinute(config('laravel-auth.rate_limits.login.attempts'))
                ->by($request->ip() . '|' . $request->input('phone_number'));
        });

        //otp rate limiter
        RateLimiter::for('laravel-auth-otp', function ($request) {
            return Limit::perMinutes(5, config('laravel-auth.rate_limits.otp_verification.attempts'))
                ->by($request->ip() . '|' . $request->input('phone_number'));
        });

        //password reset otp rate limiter
        RateLimiter::for('laravel-auth-password-reset', function ($request) {
            return Limit::perMinutes(5, config('laravel-auth.rate_limits.password_reset.attempts'))
                ->by($request->ip() . '|' . $request->input('phone_number'));
        });

        //2fa otp rate limiter
        RateLimiter::for('laravel-auth-two-factor', function ($request) {
            return Limit::perMinutes(5, config('laravel-auth.rate_limits.two_factor.attempts'))
                ->by(
                    $request->ip() . '|' . (
                        $request->input('phone_number')
                        ?? $request->input('challenge_token')
                        ?? 'unknown'
                    )
                );
        });
    }
}
