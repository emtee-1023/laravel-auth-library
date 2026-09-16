<?php

namespace Markt\LaravelAuth\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Markt\LaravelAuth\Models\Otp;
use Markt\LaravelAuth\Enums\OtpPurpose;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private OtpService $otpService,
    ) {}

    public function register(
        string $phoneNumber,
        string $password,
        array $attributes = []
    ) {
        $userModel = config('laravel-auth.models.user');

        if ($userModel::where('phone_number', $phoneNumber)->exists()) {
            throw ValidationException::withMessages([
                'phone_number' => 'A user with this phone number already exists.',
            ]);
        }

        $user = $userModel::create(array_merge(
            $attributes,
            [
                'phone_number' => $phoneNumber,
                'password' => Hash::make($password),
            ]
        ));

        $this->otpService->send($phoneNumber, OtpPurpose::Registration);

        return $user;
    }

    public function verifyPhone(string $phoneNumber, string $otp): bool
    {
        $verified = $this->otpService->verify(
            $phoneNumber,
            $otp,
            OtpPurpose::Registration
        );

        if (!$verified) {
            return false;
        }

        $userModel = config('laravel-auth.models.user');

        $user = $userModel::where('phone_number', $phoneNumber)->first();

        if (!$user) {
            return false;
        }

        $user->forceFill([
            'phone_verified_at' => now(),
        ])->save();

        return true;
    }

    public function login(string $phoneNumber, string $password): array
    {
        $userModel = config('laravel-auth.models.user');

        $user = $userModel::where(
            'phone_number',
            $phoneNumber
        )->first();

        if (
            !$user ||
            !Hash::check($password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'phone_number' => 'The provided credentials are incorrect.',
            ]);
        }

        if (!$user->phone_verified_at) {
            throw ValidationException::withMessages([
                'phone_number' => 'Your phone number has not been verified.',
            ]);
        }

        if (!config('laravel-auth.two_factor.enabled') || !$this->isTwoFactorEnabled($user)) {
            return [
                'requires_two_factor' => false,
                'token' => $user->createToken('auth-token'),
            ];
        }

        // Password is correct, but 2FA is required.
        $this->otpService->send(
            $user->phone_number,
            OtpPurpose::TwoFactor
        );

        $challengeModel = config(
            'laravel-auth.models.two_factor_challenge'
        );

        $challenge = $challengeModel::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addMinutes(
                config('laravel-auth.two_factor.challenge_expires_in')
            ),
        ]);

        return [
            'requires_two_factor' => true,
            'challenge_token' => $challenge->token,
        ];
    }

    public function verifyTwoFactorLogin(string $challengeToken, string $otp)
    {
        $challengeModel = config('laravel-auth.models.two_factor_challenge');

        $challenge = $challengeModel::where('token', $challengeToken)->first();

        if (!$challenge) {
            return null;
        }

        if ($challenge->expires_at->isPast()) {
            $challenge->delete();

            return null;
        }

        $userModel = config('laravel-auth.models.user');

        $user = $userModel::find($challenge->user_id);

        if (!$user || !$this->isTwoFactorEnabled($user)) {
            $challenge->delete();

            return null;
        }

        $verified = $this->otpService->verify(
            $user->phone_number,
            $otp,
            OtpPurpose::TwoFactor
        );

        if (!$verified) {
            return null;
        }

        // Challenge has served its purpose.
        $challenge->delete();

        return $user->createToken('auth-token');
    }

    public function logout($user, ?int $tokenId = null): void
    {
        if ($tokenId !== null) {
            $user->tokens()
                ->whereKey($tokenId)
                ->delete();

            return;
        }

        $user->currentAccessToken()?->delete();
    }

    public function requestPasswordReset(string $phoneNumber): void
    {
        $userModel = config('laravel-auth.models.user');

        $userExists = $userModel::where(
            'phone_number',
            $phoneNumber
        )->exists();


        if (!$userExists) {
            return; //This is a more secure approach since someone cannot check which phone numbers 'exist'
        }

        $this->otpService->send($phoneNumber, OtpPurpose::PasswordReset);
    }

    public function resetPassword(string $phoneNumber, string $otp, string $password): bool
    {
        if (!$this->otpService->verify($phoneNumber, $otp, OtpPurpose::PasswordReset)) {
            return false;
        }

        $userModel = config('laravel-auth.models.user');

        $user = $userModel::where(
            'phone_number',
            $phoneNumber
        )->first();

        if (!$user) {
            return false;
        }

        $user->update([
            'password' => Hash::make($password),
        ]);

        // Invalidate all existing sessions/tokens.
        $user->tokens()->delete();

        return true;
    }

    public function enableTwoFactor($user): void
    {
        $this->otpService->send(
            $user->phone_number,
            OtpPurpose::TwoFactor
        );
    }

    public function confirmTwoFactor($user, string $otp): bool
    {
        $verified = $this->otpService->verify($user->phone_number, $otp, OtpPurpose::TwoFactor);

        if (!$verified) {
            return false;
        }

        $this->setTwoFactorEnabled($user, true);

        return true;
    }

    public function disableTwoFactor($user, string $password): bool
    {
        if (!Hash::check($password, $user->password)) {
            return false;
        }

        $this->setTwoFactorEnabled($user, false);

        $challengeModel = config('laravel-auth.models.two_factor_challenge');
        $challengeModel::where('user_id', $user->id)->delete();

        return true;
    }

    private function isTwoFactorEnabled($user): bool
    {
        $settingModel = config(
            'laravel-auth.models.two_factor_setting'
        );

        return $settingModel::where('user_id', $user->id)->value('enabled') === true;
    }

    private function setTwoFactorEnabled($user, bool $enabled): void
    {
        $settingModel = config(
            'laravel-auth.models.two_factor_setting'
        );

        $settingModel::updateOrCreate(
            ['user_id' => $user->id,],
            ['enabled' => $enabled,]
        );
    }
}
