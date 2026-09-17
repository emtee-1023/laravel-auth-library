<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Models\TwoFactorChallenge;
use Markt\LaravelAuth\Tests\TestCase;

class CleanupCommandTest extends TestCase
{
    public function test_cleanup_command_removes_expired_otps_and_challenges(): void
    {
        $user = $this->createUser();

        Otp::create([
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        Otp::create([
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'expired-challenge',
            'expires_at' => now()->subMinutes(5),
        ]);

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'active-challenge',
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->artisan('laravel-auth:cleanup')->assertSuccessful();

        $this->assertSame(0, Otp::where('phone_number', '0712345678')->where('expires_at', '<', now())->count());
        $this->assertSame(1, Otp::where('phone_number', '0712345678')->count());

        $this->assertDatabaseMissing('two_factor_challenges', ['token' => 'expired-challenge']);
        $this->assertDatabaseHas('two_factor_challenges', ['token' => 'active-challenge']);
    }
}