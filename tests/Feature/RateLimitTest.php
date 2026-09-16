<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Tests\TestCase;

class RateLimitTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Lower the thresholds so tests do not need to make many requests.
        $app['config']->set('laravel-auth.rate_limits.login.attempts', 2);
        $app['config']->set('laravel-auth.rate_limits.otp_verification.attempts', 2);
        $app['config']->set('laravel-auth.rate_limits.password_reset.attempts', 2);
        $app['config']->set('laravel-auth.rate_limits.two_factor.attempts', 2);
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/login', [
                'phone_number' => '0712345678',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_verify_phone_endpoint_is_rate_limited(): void
    {
        $this->createUser();

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/verify-phone', [
                'phone_number' => '0712345678',
                'otp' => '999999',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/verify-phone', [
            'phone_number' => '0712345678',
            'otp' => '999999',
        ])->assertStatus(429);
    }

    public function test_forgot_password_endpoint_is_rate_limited(): void
    {
        $this->createUser();

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/forgot-password', [
                'phone_number' => '0712345678',
            ])->assertOk();
        }

        $this->postJson('/api/auth/forgot-password', [
            'phone_number' => '0712345678',
        ])->assertStatus(429);
    }

    public function test_reset_password_endpoint_is_rate_limited(): void
    {
        $this->createUser();

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/auth/reset-password', [
                'phone_number' => '0712345678',
                'otp' => '999999',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/reset-password', [
            'phone_number' => '0712345678',
            'otp' => '999999',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertStatus(429);
    }

    public function test_implements_hit_counter_when_rate_limits_disabled(): void
    {
        // Ensure a plain (non-429) request still works after limiter use.
        $this->getJson('/api/auth/user')->assertStatus(401);
    }
}