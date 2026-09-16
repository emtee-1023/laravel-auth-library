<?php

namespace Markt\LaravelAuth\Console\Commands;

use Illuminate\Console\Command;
use Markt\LaravelAuth\Services\OtpService;
use Markt\LaravelAuth\Services\TwoFactorChallengeService;

class LaravelAuthCleanupCommand extends Command
{
    protected $signature = 'laravel-auth:cleanup';

    protected $description = 'Remove expired OTPs and two-factor authentication challenges';

    public function handle(
        OtpService $otpService,
        TwoFactorChallengeService $twoFactorChallengeService
    ): int {
        $deletedOtps = $otpService->cleanupExpired();

        $deletedChallenges = $twoFactorChallengeService->cleanupExpired();

        $this->info("Deleted {$deletedOtps} expired OTP(s).");
        $this->info("Deleted {$deletedChallenges} expired 2FA challenge(s).");

        return self::SUCCESS;
    }
}
