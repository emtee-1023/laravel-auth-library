<?php

namespace Markt\LaravelAuth\Services;

use Illuminate\Support\Str;

class TwoFactorChallengeService
{
    public function create($user)
    {
        $challengeModel = config(
            'laravel-auth.models.two_factor_challenge'
        );

        // Only one active challenge per user.
        $challengeModel::where(
            'user_id',
            $user->id
        )->delete();

        return $challengeModel::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(
                config('laravel-auth.two_factor.challenge_expires_in')
            ),
        ]);
    }

    public function findValid(string $token)
    {
        $challengeModel = config(
            'laravel-auth.models.two_factor_challenge'
        );

        return $challengeModel::where(
            'token',
            $token
        )
            ->where('expires_at', '>', now())
            ->first();
    }

    public function renew($challenge): void
    {
        $challenge->update([
            'expires_at' => now()->addMinutes(
                config('laravel-auth.two_factor.challenge_expires_in')
            ),
        ]);
    }

    public function consume($challenge): void
    {
        $challenge->delete();
    }

    public function cleanupExpired(): int
    {
        $challengeModel = config(
            'laravel-auth.models.two_factor_challenge'
        );

        return $challengeModel::where(
            'expires_at',
            '<',
            now()
        )->delete();
    }
}
