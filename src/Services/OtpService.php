<?php

namespace Markt\LaravelAuth\Services;

use Markt\LaravelAuth\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Enums\OtpPurpose;

class OtpService
{
    public function __construct(private SmsSender $smsSender) {}

    private function otpModel()
    {
        return config('laravel-auth.models.otp');
    }

    private function generateOtp(): string
    {
        $length = config('laravel-auth.otp.length');
        $min = $length ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    public function send(string $phoneNumber, OtpPurpose $purpose): void
    {
        $otpModel = $this->otpModel();

        $otpModel::where('phone_number', $phoneNumber)
            ->where('purpose', $purpose->value)
            ->whereNull('verified_at')
            ->update([
                'verified_at' => now(),
            ]);

        // generate + save + send...
        $expiresAt = now()->addMinutes(config('laravel-auth.otp.expires_in'));
        $otp = $this->generateOtp();

        // Hash the otp
        $otpHash = Hash::make($otp);

        //Save the hashed otp
        $otpModel::create([
            'phone_number' => $phoneNumber,
            'purpose' => $purpose->value,
            'otp_hash' => $otpHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
            'verified_at' => null,
        ]);

        //Send OTP as Sms
        $this->smsSender->send(
            $phoneNumber,
            "Your verification code is {$otp}"
        );
    }

    public function verify(string $phoneNumber, string $otp, OtpPurpose $purpose): bool
    {
        $otpModel = $this->otpModel();

        $otpRecord = $otpModel::where('phone_number', $phoneNumber)
            ->where('purpose', $purpose->value)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where(
                'attempts',
                '<',
                config('laravel-auth.otp.max_attempts')
            )
            ->latest()
            ->first();


        if (!$otpRecord) {
            return false;
        }

        $otpRecord->increment('attempts');

        if (!Hash::check($otp, $otpRecord->otp_hash)) {
            return false;
        }

        $otpRecord->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    public function cleanupExpired(): int
    {
        $otpModel = $this->otpModel();

        return $otpModel::where('expires_at', '<', now())->delete();
    }
}
