<?php

namespace Markt\LaravelAuth\Services;

use Markt\LaravelAuth\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;
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

    public function send(string $phoneNumber): void
    {
        //variables
        $expiresAt = now()->addMinutes(config('laravel-auth.otp.expires_in'));
        $otp = $this->generateOtp();

        // Hash the otp
        $otpHash = Hash::make($otp);

        //Save the hashed otp
        Otp::create([
            'phone_number' => $phoneNumber,
            'otp_hash' => $otpHash,
            'expires_at' => $expiresAt,
            'attempts' => 0,
        ]);

        //Send OTP as Sms
        $this->smsSender->send(
            $phoneNumber,
            "Your verification code is {$otp}"
        );
    }

    public function verify(string $phoneNumber, string $otp): bool
    {
        $otpRecord = Otp::where('phone_number', $phoneNumber)
            ->latest()
            ->first();

        if (! $otpRecord) {
            return false;
        }

        if ($otpRecord->expires_at->isPast()) {
            return false;
        }

        if ($otpRecord->attempts >= config('laravel-auth.otp.max_attempts')) {
            return false;
        }

        $otpRecord->increment('attempts');

        return Hash::check($otp, $otpRecord->otp_hash);
    }
}
