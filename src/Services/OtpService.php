<?php

namespace Markt\LaravelAuth\Services;

use Markt\LaravelAuth\Contracts\SmsSender;

class OtpService
{
    public function __construct(private SmsSender $smsSender) {}

    public function send(string $phoneNumber): void
    {
        //Get the otp
        $otp = $this->generateOtp();

        // Save OTP...
        //TODO write code for saving

        //Send OTP as Sms
        $this->smsSender->send(
            $phoneNumber,
            "Your verification code is {$otp}"
        );
    }

    private function generateOtp(): string
    {
        return "676767";
    }
}
