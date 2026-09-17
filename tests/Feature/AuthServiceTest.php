<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Models\TwoFactorChallenge;
use Markt\LaravelAuth\Models\TwoFactorSetting;
use Markt\LaravelAuth\Services\AuthService;
use Markt\LaravelAuth\Tests\TestCase;

class AuthServiceTest extends TestCase
{
    protected function seedRegistrationOtp(string $phoneNumber, string $code): void
    {
        Otp::where('phone_number', $phoneNumber)
            ->where('purpose', OtpPurpose::Registration->value)
            ->delete();

        Otp::create([
            'phone_number' => $phoneNumber,
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);
    }

    protected function seedPasswordResetOtp(string $phoneNumber, string $code): void
    {
        Otp::where('phone_number', $phoneNumber)
            ->where('purpose', OtpPurpose::PasswordReset->value)
            ->delete();

        Otp::create([
            'phone_number' => $phoneNumber,
            'purpose' => OtpPurpose::PasswordReset->value,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);
    }

    protected function seedTwoFactorOtp(string $phoneNumber, string $code): void
    {
        Otp::where('phone_number', $phoneNumber)
            ->where('purpose', OtpPurpose::TwoFactor->value)
            ->delete();

        Otp::create([
            'phone_number' => $phoneNumber,
            'purpose' => OtpPurpose::TwoFactor->value,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);
    }

    public function test_register_creates_user_with_hashed_password_and_sends_registration_otp(): void
    {
        $service = app(AuthService::class);

        $user = $service->register(
            '0712345678',
            'password123',
            ['name' => 'Jane Doe']
        );

        $this->assertNotNull($user);
        $this->assertSame('0712345678', $user->phone_number);
        $this->assertSame('Jane Doe', $user->name);
        $this->assertNotSame('password123', $user->password);
        $this->assertNull($user->phone_verified_at);

        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
        ]);
    }

    public function test_register_rejects_duplicate_phone_number(): void
    {
        $this->createUser(['phone_number' => '0712345678']);

        $this->expectException(ValidationException::class);

        app(AuthService::class)->register('0712345678', 'password123');
    }

    public function test_verify_phone_marks_user_as_verified_with_correct_otp(): void
    {
        $user = $this->createUser(['phone_verified_at' => null]);
        $this->seedRegistrationOtp($user->phone_number, '123456');

        $verified = app(AuthService::class)->verifyPhone($user->phone_number, '123456');

        $this->assertTrue($verified);
        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_verify_phone_returns_false_with_an_invalid_otp(): void
    {
        $user = $this->createUser(['phone_verified_at' => null]);
        $this->seedRegistrationOtp($user->phone_number, '123456');

        $verified = app(AuthService::class)->verifyPhone($user->phone_number, '999999');

        $this->assertFalse($verified);
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_verify_phone_returns_false_without_an_otp_record(): void
    {
        $user = $this->createUser(['phone_verified_at' => null]);

        $verified = app(AuthService::class)->verifyPhone($user->phone_number, '123456');

        $this->assertFalse($verified);
    }

    public function test_login_returns_token_for_verified_user(): void
    {
        $user = $this->createUser();

        $result = app(AuthService::class)->login('0712345678', 'password123');

        $this->assertFalse($result['requires_two_factor']);
        $this->assertNotNull($result['token']);
    }

    public function test_login_throws_for_incorrect_credentials(): void
    {
        $this->createUser();

        $this->expectException(ValidationException::class);

        app(AuthService::class)->login('0712345678', 'wrong-password');
    }

    public function test_login_throws_for_unverified_phone_number(): void
    {
        $this->createUser(['phone_verified_at' => null]);

        $this->expectException(ValidationException::class);

        app(AuthService::class)->login('0712345678', 'password123');
    }

    public function test_login_returns_2fa_challenge_when_user_has_2fa_enabled(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $result = app(AuthService::class)->login('0712345678', 'password123');

        $this->assertTrue($result['requires_two_factor']);
        $this->assertNotEmpty($result['challenge_token']);
    }

    public function test_login_skips_2fa_when_globally_disabled(): void
    {
        $this->app['config']->set('laravel-auth.two_factor.enabled', false);

        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $result = app(AuthService::class)->login('0712345678', 'password123');

        $this->assertFalse($result['requires_two_factor']);
        $this->assertNotNull($result['token']);
    }

    public function test_verify_two_factor_login_returns_token_with_valid_challenge_and_otp(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $login = app(AuthService::class)->login('0712345678', 'password123');

        $this->seedTwoFactorOtp($user->phone_number, '123456');

        $token = app(AuthService::class)->verifyTwoFactorLogin($login['challenge_token'], '123456');

        $this->assertNotNull($token);

        $this->assertDatabaseMissing('two_factor_challenges', ['token' => $login['challenge_token']]);
    }

    public function test_verify_two_factor_login_returns_null_for_expired_challenge(): void
    {
        $user = $this->createUser();

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'expired-challenge',
            'expires_at' => now()->subMinutes(5),
        ]);

        $token = app(AuthService::class)->verifyTwoFactorLogin('expired-challenge', '123456');

        $this->assertNull($token);

        // An expired challenge is invisible to findValid(), so it is never
        // consumed here — the cleanup command removes expired challenges.
        $this->assertDatabaseHas('two_factor_challenges', ['token' => 'expired-challenge']);
    }

    public function test_verify_two_factor_login_returns_null_for_wrong_otp(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $login = app(AuthService::class)->login('0712345678', 'password123');

        $this->seedTwoFactorOtp($user->phone_number, '123456');

        $token = app(AuthService::class)->verifyTwoFactorLogin($login['challenge_token'], '999999');

        $this->assertNull($token);
    }

    public function test_logout_deletes_the_current_access_token(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('auth-token');
        $accessToken = $token->accessToken;

        $user->withAccessToken($accessToken);

        app(AuthService::class)->logout($user);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $accessToken->id]);
    }

    public function test_request_password_reset_sends_an_otp_for_existing_user(): void
    {
        $this->createUser();

        app(AuthService::class)->requestPasswordReset('0712345678');

        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::PasswordReset->value,
        ]);
    }

    public function test_request_password_reset_does_not_send_for_unknown_number(): void
    {
        app(AuthService::class)->requestPasswordReset('0799998888');

        $this->assertDatabaseMissing('otps', ['phone_number' => '0799998888']);
    }

    public function test_reset_password_updates_password_and_invalidates_tokens(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('auth-token');

        $this->seedPasswordResetOtp($user->phone_number, '123456');

        $reset = app(AuthService::class)->resetPassword(
            '0712345678',
            '123456',
            'new-password-1',
        );

        $this->assertTrue($reset);
        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_reset_password_returns_false_for_wrong_otp(): void
    {
        $this->createUser();
        $this->seedPasswordResetOtp('0712345678', '123456');

        $reset = app(AuthService::class)->resetPassword(
            '0712345678',
            '999999',
            'new-password-1',
        );

        $this->assertFalse($reset);
    }

    public function test_confirm_two_factor_enables_setting_with_valid_otp(): void
    {
        $user = $this->createUser();
        $this->seedTwoFactorOtp($user->phone_number, '123456');

        $confirmed = app(AuthService::class)->confirmTwoFactor($user, '123456');

        $this->assertTrue($confirmed);
        $this->assertTrue((bool) TwoFactorSetting::where('user_id', $user->id)->value('enabled'));
    }

    public function test_confirm_two_factor_returns_false_for_wrong_otp(): void
    {
        $user = $this->createUser();
        $this->seedTwoFactorOtp($user->phone_number, '123456');

        $confirmed = app(AuthService::class)->confirmTwoFactor($user, '999999');

        $this->assertFalse($confirmed);
    }

    public function test_disable_two_factor_requires_correct_password(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $disabled = app(AuthService::class)->disableTwoFactor($user, 'password123');

        $this->assertTrue($disabled);
        $this->assertFalse((bool) TwoFactorSetting::where('user_id', $user->id)->value('enabled'));
    }

    public function test_disable_two_factor_rejects_wrong_password(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $disabled = app(AuthService::class)->disableTwoFactor($user, 'wrong-password');

        $this->assertFalse($disabled);
        $this->assertTrue((bool) TwoFactorSetting::where('user_id', $user->id)->value('enabled'));
    }
}