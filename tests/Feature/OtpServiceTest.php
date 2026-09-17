<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Services\OtpService;
use Markt\LaravelAuth\Tests\TestCase;

class OtpServiceTest extends TestCase
{
    private function captureOtp(OtpService $service, string $phoneNumber, OtpPurpose $purpose): string
    {
        $service->send($phoneNumber, $purpose);

        $message = $this->fakeSmsSender()->messagesFor($phoneNumber);

        preg_match('/Your verification code is (\d+)/', $message, $matches);

        return $matches[1];
    }

    public function test_send_generates_stores_and_dispatches_a_new_code(): void
    {
        $service = app(OtpService::class);

        $code = $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $otp = Otp::where('phone_number', '0712345678')->first();

        $this->assertNotNull($otp);
        $this->assertSame(OtpPurpose::Registration->value, $otp->purpose);
        $this->assertTrue(Hash::check($code, $otp->otp_hash));
        $this->assertSame(0, (int) $otp->attempts);
        $this->assertNull($otp->verified_at);
        $this->assertTrue($otp->expires_at->isFuture());
        $this->assertEquals(1, $this->fakeSmsSender()->countFor('0712345678'));
    }

    public function test_send_invalidates_all_previous_unverified_codes_for_the_same_purpose(): void
    {
        $service = app(OtpService::class);

        $this->captureOtp($service, '0712345678', OtpPurpose::Registration);
        $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $otps = Otp::where('phone_number', '0712345678')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $otps);
        $this->assertNotNull($otps[0]->verified_at);
        $this->assertNull($otps[1]->verified_at);
    }

    public function test_different_purposes_are_not_invalidated_by_each_other(): void
    {
        $service = app(OtpService::class);

        $this->captureOtp($service, '0712345678', OtpPurpose::Registration);
        $this->captureOtp($service, '0712345678', OtpPurpose::PasswordReset);

        $registration = Otp::where('phone_number', '0712345678')
            ->where('purpose', OtpPurpose::Registration->value)
            ->first();
        $reset = Otp::where('phone_number', '0712345678')
            ->where('purpose', OtpPurpose::PasswordReset->value)
            ->first();

        $this->assertNull($registration->verified_at);
        $this->assertNull($reset->verified_at);
    }

    public function test_verify_returns_true_and_marks_record_verified_for_a_correct_code(): void
    {
        $service = app(OtpService::class);
        $code = $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $this->assertTrue($service->verify('0712345678', $code, OtpPurpose::Registration));

        $otp = Otp::where('phone_number', '0712345678')->first();
        $this->assertNotNull($otp->verified_at);
    }

    public function test_verify_returns_false_and_increments_attempts_for_a_wrong_code(): void
    {
        $service = app(OtpService::class);
        $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $this->assertFalse($service->verify('0712345678', '000000', OtpPurpose::Registration));

        $otp = Otp::where('phone_number', '0712345678')->first();
        $this->assertSame(1, (int) $otp->attempts);
        $this->assertNull($otp->verified_at);
    }

    public function test_verify_is_case_and_purpose_sensitive(): void
    {
        $service = app(OtpService::class);
        $code = $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $this->assertFalse($service->verify('0712345678', $code, OtpPurpose::TwoFactor));
    }

    public function test_verify_returns_false_for_an_expired_code(): void
    {
        Otp::create([
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(1),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        $service = app(OtpService::class);

        $this->assertFalse($service->verify('0712345678', '123456', OtpPurpose::Registration));
    }

    public function test_verify_returns_false_when_max_attempts_have_been_exceeded(): void
    {
        Otp::create([
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 5,
            'verified_at' => null,
        ]);

        $service = app(OtpService::class);

        $this->assertFalse($service->verify('0712345678', '123456', OtpPurpose::Registration));
    }

    public function test_verify_returns_false_when_code_has_already_been_consumed(): void
    {
        Otp::create([
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => now()->subMinutes(1),
        ]);

        $service = app(OtpService::class);

        $this->assertFalse($service->verify('0712345678', '123456', OtpPurpose::Registration));
    }

    public function test_uses_the_configured_otp_model(): void
    {
        $this->app['config']->set('laravel-auth.models.otp', Otp::class);

        $service = app(OtpService::class);

        $this->captureOtp($service, '0712345678', OtpPurpose::Registration);

        $this->assertDatabaseHas('otps', [
            'phone_number' => '0712345678',
            'purpose' => OtpPurpose::Registration->value,
        ]);
    }

    public function test_cleanup_expired_rejects_expired_and_keeps_active_records(): void
    {
        Otp::create([
            'phone_number' => '0799999999',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(1),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        Otp::create([
            'phone_number' => '0788888888',
            'purpose' => OtpPurpose::Registration->value,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'verified_at' => null,
        ]);

        $service = app(OtpService::class);

        $this->assertSame(1, $service->cleanupExpired());
        $this->assertDatabaseMissing('otps', ['phone_number' => '0799999999']);
        $this->assertDatabaseHas('otps', ['phone_number' => '0788888888']);
    }
}