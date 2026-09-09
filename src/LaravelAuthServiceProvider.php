<?php

namespace Markt\LaravelAuth;

use Illuminate\Support\ServiceProvider;

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
        $this->publishes([
            __DIR__ . '/../config/laravel-auth.php' => config_path('laravel-auth.php'),
        ], 'laravel-auth-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laravel-auth-migrations');
    }
}
