<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Markt\LaravelAuth\Contracts\SmsSender;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Services\OtpService;
use Markt\LaravelAuth\Tests\TestCase;

class OtpServiceTest extends TestCase
{
    public function test_it_sends_and_stores_an_otp(): void
    {
        $smsSender = $this->mock(SmsSender::class);

        $smsSender
            ->shouldReceive('send')
            ->once()
            ->withArgs(function (string $phoneNumber, string $message) {
                return $phoneNumber === '0712345678'
                    && preg_match('/Your verification code is \d{6}/', $message);
            });

        $service = app(OtpService::class);

        $service->send('0712345678');

        $otp = Otp::first();

        $this->assertNotNull($otp);
        $this->assertEquals('0712345678', $otp->phone_number);
        $this->assertNotEmpty($otp->otp_hash);
        $this->assertEquals(0, $otp->attempts);
        $this->assertNull($otp->verified_at);
        $this->assertFalse(Hash::check('123456', $otp->otp_hash));
        $this->assertTrue($otp->expires_at->isFuture());
    }
}
