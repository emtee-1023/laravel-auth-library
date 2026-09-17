<?php

namespace Markt\LaravelAuth\Tests\Feature;

use Markt\LaravelAuth\Models\TwoFactorChallenge;
use Markt\LaravelAuth\Services\TwoFactorChallengeService;
use Markt\LaravelAuth\Tests\TestCase;

class TwoFactorChallengeServiceTest extends TestCase
{
    public function test_create_stores_a_non_expired_challenge_for_the_user(): void
    {
        $user = $this->createUser();

        $service = app(TwoFactorChallengeService::class);

        $challenge = $service->create($user);

        $this->assertNotNull($challenge->token);
        $this->assertSame($user->id, $challenge->user_id);
        $this->assertTrue($challenge->expires_at->isFuture());
    }

    public function test_create_keeps_only_one_active_challenge_per_user(): void
    {
        $user = $this->createUser();

        $service = app(TwoFactorChallengeService::class);

        $first = $service->create($user);
        $second = $service->create($user);

        $this->assertDatabaseHas('two_factor_challenges', ['id' => $second->id]);
        $this->assertDatabaseMissing('two_factor_challenges', ['id' => $first->id]);
    }

    public function test_find_valid_returns_the_challenge_for_a_valid_token(): void
    {
        $user = $this->createUser();

        $service = app(TwoFactorChallengeService::class);

        $challenge = $service->create($user);

        $found = $service->findValid($challenge->token);

        $this->assertNotNull($found);
        $this->assertSame($challenge->id, $found->id);
    }

    public function test_find_valid_returns_null_for_an_expired_token(): void
    {
        $user = $this->createUser();

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'expired-token',
            'expires_at' => now()->subMinutes(5),
        ]);

        $service = app(TwoFactorChallengeService::class);

        $this->assertNull($service->findValid('expired-token'));
    }

    public function test_find_valid_returns_null_for_unknown_token(): void
    {
        $service = app(TwoFactorChallengeService::class);

        $this->assertNull($service->findValid('does-not-exist'));
    }

    public function test_renew_refreshes_the_challenge_expiry(): void
    {
        $user = $this->createUser();

        $challenge = app(TwoFactorChallengeService::class)->create($user);

        $challenge->update(['expires_at' => now()->addSeconds(5)]);

        app(TwoFactorChallengeService::class)->renew($challenge);

        $this->assertTrue(
            $challenge->fresh()->expires_at->greaterThan(now()->addMinutes(4))
        );
    }

    public function test_consume_deletes_the_challenge(): void
    {
        $user = $this->createUser();

        $service = app(TwoFactorChallengeService::class);

        $challenge = $service->create($user);

        $service->consume($challenge);

        $this->assertDatabaseMissing('two_factor_challenges', ['id' => $challenge->id]);
    }

    public function test_cleanup_expired_deletes_only_expired_challenges(): void
    {
        $user = $this->createUser();

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'expired-1',
            'expires_at' => now()->subMinutes(5),
        ]);

        TwoFactorChallenge::create([
            'user_id' => $user->id,
            'token' => 'active-1',
            'expires_at' => now()->addMinutes(5),
        ]);

        $deleted = app(TwoFactorChallengeService::class)->cleanupExpired();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('two_factor_challenges', ['token' => 'expired-1']);
        $this->assertDatabaseHas('two_factor_challenges', ['token' => 'active-1']);
    }
}