<?php

namespace Markt\LaravelAuth\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Markt\LaravelAuth\Models\Otp;

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

        $this->otpService->send($phoneNumber);

        return $user;
    }

    public function verifyPhone(
        string $phoneNumber,
        string $otp
    ): bool {
        $verified = $this->otpService->verify(
            $phoneNumber,
            $otp
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

    public function login(
        string $phoneNumber,
        string $password
    ) {
        $userModel = config('laravel-auth.models.user');

        $user = $userModel::where('phone_number', $phoneNumber)->first();

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

        $this->otpService->send($phoneNumber);
    }

    public function resetPassword(string $phoneNumber, string $otp, string $password): bool
    {
        if (!$this->otpService->verify($phoneNumber, $otp)) {
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
}
