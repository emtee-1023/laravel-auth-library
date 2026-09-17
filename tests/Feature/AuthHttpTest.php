<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Models\TwoFactorSetting;
use Markt\LaravelAuth\Tests\TestCase;

class AuthHttpTest extends TestCase
{
    protected function seedOtp(string $phoneNumber, string $purpose, string $code = '123456'): void
    {
        Otp::where('phone_number', $phoneNumber)
            ->where('purpose', $purpose)
            ->delete();

        Otp::create([
            'phone_number' => $phoneNumber,
            'purpose' => $purpose,
            'otp_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);
    }

    public function test_register_endpoint_returns_201_and_stores_a_user_and_otp(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'phone_number' => '0712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('users', ['phone_number' => '0712345678']);
        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
        ]);
    }

    public function test_register_endpoint_validates_input(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'phone_number' => '',
            'password' => 'short',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_register_endpoint_rejects_duplicate_phone_number(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'phone_number' => '0712345678',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('phone_number', $response->json('errors'));
    }

    public function test_verify_phone_endpoint_marks_user_verified(): void
    {
        $user = $this->createUser(['phone_verified_at' => null]);
        $this->seedOtp($user->phone_number, OtpPurpose::Registration->value);

        $response = $this->postJson('/api/auth/verify-phone', [
            'phone_number' => '0712345678',
            'otp' => '123456',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Phone number verified successfully.']);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_verify_phone_endpoint_rejects_invalid_otp(): void
    {
        $this->createUser(['phone_verified_at' => null]);
        $this->seedOtp('0712345678', OtpPurpose::Registration->value);

        $response = $this->postJson('/api/auth/verify-phone', [
            'phone_number' => '0712345678',
            'otp' => '999999',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired verification code.']);
    }

    public function test_login_endpoint_returns_bearer_token(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['token']);
    }

    public function test_login_endpoint_returns_2fa_challenge_for_enabled_user(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $response = $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'requires_two_factor' => true,
            ])
            ->assertJsonStructure(['challenge_token']);
    }

    public function test_login_endpoint_rejects_invalid_credentials(): void
    {
        $this->createUser();

        $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_endpoint_rejects_unverified_user(): void
    {
        $this->createUser(['phone_verified_at' => null]);

        $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'password123',
        ])->assertStatus(422);
    }

    public function test_forgot_password_endpoint_returns_generic_message_for_unknown_number(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'phone_number' => '0799998888',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If an account exists for this phone number, a verification code has been sent.',
            ]);

        $this->assertDatabaseMissing('otps', ['phone_number' => '0799998888']);
    }

    public function test_forgot_password_endpoint_sends_reset_otp_for_existing_user(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/forgot-password', [
            'phone_number' => '0712345678',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::PasswordReset->value,
        ]);
    }

    public function test_resend_otp_endpoint_resends_registration_code(): void
    {
        $this->createUser(['phone_verified_at' => null]);
        $this->seedOtp('0712345678', OtpPurpose::Registration->value);

        $response = $this->postJson('/api/auth/resend-otp', [
            'purpose' => 'registration',
            'phone_number' => '0712345678',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'A new verification code has been sent to your phone.']);

        $otps = Otp::where('phone_number', '0712345678')
            ->where('purpose', OtpPurpose::Registration->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $otps);
        $this->assertNotNull($otps[0]->verified_at);
        $this->assertNull($otps[1]->verified_at);
    }

    public function test_resend_otp_endpoint_resends_password_reset_code(): void
    {
        $this->createUser();
        $this->seedOtp('0712345678', OtpPurpose::PasswordReset->value);

        $response = $this->postJson('/api/auth/resend-otp', [
            'purpose' => 'password_reset',
            'phone_number' => '0712345678',
        ]);

        $response->assertOk();

        $otps = Otp::where('phone_number', '0712345678')
            ->where('purpose', OtpPurpose::PasswordReset->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $otps);
        $this->assertNotNull($otps[0]->verified_at);
        $this->assertNull($otps[1]->verified_at);
    }

    public function test_resend_otp_endpoint_does_not_leak_for_unknown_number(): void
    {
        $response = $this->postJson('/api/auth/resend-otp', [
            'purpose' => 'password_reset',
            'phone_number' => '0799998888',
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('otps', ['phone_number' => '0799998888']);
    }

    public function test_resend_otp_endpoint_resends_2fa_code_for_valid_challenge(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $challengeToken = $this->postJson('/api/auth/login', [
            'phone_number' => '0712345678',
            'password' => 'password123',
        ])->json('challenge_token');

        $response = $this->postJson('/api/auth/resend-otp', [
            'purpose' => 'two_factor',
            'challenge_token' => $challengeToken,
        ]);

        $response->assertOk();

        $otps = Otp::where('phone_number', '0712345678')
            ->where('purpose', OtpPurpose::TwoFactor->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $otps);
        $this->assertNotNull($otps[0]->verified_at);
        $this->assertNull($otps[1]->verified_at);
    }

    public function test_resend_otp_endpoint_rejects_invalid_2fa_challenge(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/auth/resend-otp', [
            'purpose' => 'two_factor',
            'challenge_token' => 'invalid-token',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired verification code.']);
    }

    public function test_resend_otp_endpoint_validates_input(): void
    {
        $this->postJson('/api/auth/resend-otp', [
            'purpose' => '',
        ])->assertStatus(422);
    }

    public function test_reset_password_endpoint_updates_password(): void
    {
        $user = $this->createUser();
        $this->seedOtp($user->phone_number, OtpPurpose::PasswordReset->value);

        $response = $this->postJson('/api/auth/reset-password', [
            'phone_number' => '0712345678',
            'otp' => '123456',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password reset successfully.']);

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_reset_password_endpoint_rejects_invalid_otp(): void
    {
        $this->createUser();
        $this->seedOtp('0712345678', OtpPurpose::PasswordReset->value);

        $response = $this->postJson('/api/auth/reset-password', [
            'phone_number' => '0712345678',
            'otp' => '999999',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired verification code.']);
    }

    public function test_authenticated_user_can_fetch_current_user(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->getJson('/api/auth/user');

        $response->assertOk()
            ->assertJsonPath('user.phone_number', '0712345678');
    }

    public function test_unauthenticated_request_is_rejected_with_401(): void
    {
        $this->getJson('/api/auth/user')->assertStatus(401);
    }

    public function test_logout_endpoint_revokes_token(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('auth-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_enable_2fa_endpoint_sends_2fa_otp(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/enable')
            ->assertOk()
            ->assertJson(['message' => 'A verification code has been sent to your phone.']);

        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::TwoFactor->value,
        ]);
    }

    public function test_confirm_2fa_endpoint_enables_two_factor(): void
    {
        $user = $this->createUser();
        $this->seedOtp($user->phone_number, OtpPurpose::TwoFactor->value);

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirm', ['otp' => '123456'])
            ->assertOk()
            ->assertJson(['message' => 'Two-factor authentication enabled successfully.']);

        $this->assertTrue((bool) TwoFactorSetting::where('user_id', $user->id)->value('enabled'));
    }

    public function test_confirm_2fa_endpoint_rejects_invalid_otp(): void
    {
        $user = $this->createUser();
        $this->seedOtp($user->phone_number, OtpPurpose::TwoFactor->value);

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirm', ['otp' => '999999'])
            ->assertStatus(422);
    }

    public function test_disable_2fa_endpoint_disables_two_factor(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/disable', ['password' => 'password123'])
            ->assertOk()
            ->assertJson(['message' => 'Two-factor authentication disabled successfully.']);

        $this->assertFalse((bool) TwoFactorSetting::where('user_id', $user->id)->value('enabled'));
    }

    public function test_disable_2fa_endpoint_rejects_wrong_password(): void
    {
        $user = $this->createUser();

        TwoFactorSetting::updateOrCreate(
            ['user_id' => $user->id],
            ['enabled' => true],
        );

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/disable', ['password' => 'wrong-password'])
            ->assertStatus(422);
    }

    public function test_complete_registration_verification_and_login_flow(): void
    {
        $phone = '0711112222';

        $this->postJson('/api/auth/register', [
            'name' => 'Flow User',
            'phone_number' => $phone,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        // Registration dispatches a random OTP through the SMS sender; seed a
        // known one so the remainder of the flow is deterministic.
        $this->seedOtp($phone, OtpPurpose::Registration->value, '123456');

        $this->postJson('/api/auth/verify-phone', [
            'phone_number' => $phone,
            'otp' => '123456',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'phone_number' => $phone,
            'password' => 'password123',
        ])->assertOk()
            ->assertJson(['token_type' => 'Bearer'])
            ->assertJsonStructure(['token']);
    }
}