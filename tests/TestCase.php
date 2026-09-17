<?php

namespace Markt\LaravelAuth\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\SanctumServiceProvider;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\LaravelAuthServiceProvider;
use Markt\LaravelAuth\Tests\Fixtures\FakeSmsSender;
use Markt\LaravelAuth\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LaravelAuthServiceProvider::class,
            SanctumServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');

        // Point the auth infrastructure at a Sanctum-capable test model.
        $app['config']->set('auth.defaults.guard', 'sanctum');
        $app['config']->set('auth.guards.sanctum', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('laravel-auth.models.user', User::class);
        $app['config']->set('laravel-auth.models.otp', \Markt\LaravelAuth\Models\Otp::class);
        $app['config']->set(
            'laravel-auth.models.two_factor_challenge',
            \Markt\LaravelAuth\Models\TwoFactorChallenge::class
        );
        $app['config']->set(
            'laravel-auth.models.two_factor_setting',
            \Markt\LaravelAuth\Models\TwoFactorSetting::class
        );

        $app['config']->set('laravel-auth.otp.length', 6);
        $app['config']->set('laravel-auth.otp.expires_in', 5);
        $app['config']->set('laravel-auth.otp.max_attempts', 5);

        $app['config']->set('laravel-auth.two_factor.enabled', true);
        $app['config']->set('laravel-auth.two_factor.challenge_expires_in', 5);

        $app->instance(SmsSender::class, new FakeSmsSender());
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Fixtures/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function fakeSmsSender(): FakeSmsSender
    {
        return $this->app->make(SmsSender::class);
    }

    protected function lastOtpFor(string $phoneNumber): string
    {
        $message = $this->fakeSmsSender()->messagesFor($phoneNumber);
        preg_match('/Your verification code is (\d+)/', $message, $matches);

        return $matches[1] ?? '';
    }

    protected function createUser(array $attributes = []): User
    {
        return User::create(array_merge([
            'phone_number' => '0712345678',
            'name' => 'Test User',
            'password' => bcrypt('password123'),
            'phone_verified_at' => now(),
        ], $attributes));
    }
}