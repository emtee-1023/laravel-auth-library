<?php

namespace Markt\LaravelAuth\Services;

use Markt\LaravelAuth\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Markt\LaravelAuth\Models\Otp;

class OtpService
{
    public function __construct(private SmsSender $smsSender) {}

    private function generateOtp(): string
    {
        $length = config('laravel-auth.otp.length');
        $min = $length ** ($length - 1);
        $max = (10 ** $length) - 1;

        return (string) random_int($min, $max);
    }

    public function send(string $phoneNumber, OtpPurpose $purpose): void
    {
        Otp::where('phone_number', $phoneNumber)
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
        Otp::create([
            'phone_number' => $phoneNumber,
            'purpose' => $purpose->value,
            'otp_hash' => Hash::make($otp),
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
        $otpRecord = Otp::where('phone_number', $phoneNumber)
            ->where('purpose', $purpose->value)
            ->latest()
            ->first();

        if (! $otpRecord) {
            return false;
        }

        if ($otpRecord->verified_at !== null) {
            return false;
        }

        if ($otpRecord->expires_at->isPast()) {
            return false;
        }

        if ($otpRecord->attempts >= config('laravel-auth.otp.max_attempts')) {
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
}
