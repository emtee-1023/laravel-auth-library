<?php

namespace Markt\LaravelAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Markt\LaravelAuth\Http\Requests\LoginRequest;
use Markt\LaravelAuth\Http\Requests\RegisterRequest;
use Markt\LaravelAuth\Http\Requests\VerifyPhoneRequest;
use Markt\LaravelAuth\Http\Requests\ForgotPasswordRequest;
use Markt\LaravelAuth\Http\Requests\ResetPasswordRequest;
use Markt\LaravelAuth\Services\AuthService;
use Markt\LaravelAuth\Http\Requests\EnableTwoFactorRequest;
use Markt\LaravelAuth\Http\Requests\ConfirmTwoFactorRequest;
use Markt\LaravelAuth\Http\Requests\DisableTwoFactorRequest;
use Markt\LaravelAuth\Http\Requests\VerifyTwoFactorRequest;
use Markt\LaravelAuth\Http\Requests\ResendOtpRequest;

class AuthController
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register(
            phoneNumber: $request->string('phone_number')->toString(),
            password: $request->string('password')->toString(),
            attributes: [
                'name' => $request->string('name')->toString(),
            ],
        );

        return response()->json([
            'message' => 'Registration successful. Please verify your phone number.',
            'user' => $user,
        ], 201);
    }

    public function verifyPhone(VerifyPhoneRequest $request): JsonResponse
    {
        $verified = $this->authService->verifyPhone(
            phoneNumber: $request->string('phone_number')->toString(),
            otp: $request->string('otp')->toString(),
        );

        if (!$verified) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Phone number verified successfully.',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            phoneNumber: $request->string('phone_number')->toString(),
            password: $request->string('password')->toString(),
        );

        if ($result['requires_two_factor']) {
            return response()->json([
                'message' => 'Two-factor authentication required.',
                'requires_two_factor' => true,
                'challenge_token' => $result['challenge_token'],
            ]);
        }

        return response()->json([
            'message' => 'Login successful.',
            'token' => $result['token']->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->requestPasswordReset(
            $request->string('phone_number')->toString(),
        );

        return response()->json([
            'message' => 'If an account exists for this phone number, a verification code has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $reset = $this->authService->resetPassword(
            phoneNumber: $request->string('phone_number')->toString(),
            otp: $request->string('otp')->toString(),
            password: $request->string('password')->toString(),
        );

        if (!$reset) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        $sent = $this->authService->resendOtp(
            purpose: $request->string('purpose')->toString(),
            phoneNumber: $request->string('phone_number')?->toString(),
            challengeToken: $request->string('challenge_token')?->toString(),
        );

        if (!$sent) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'A new verification code has been sent to your phone.',
        ]);
    }

    public function enableTwoFactor(EnableTwoFactorRequest $request): JsonResponse
    {
        $this->authService->enableTwoFactor(
            $request->user()
        );

        return response()->json([
            'message' => 'A verification code has been sent to your phone.',
        ]);
    }

    public function confirmTwoFactor(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $confirmed = $this->authService->confirmTwoFactor(
            user: $request->user(),
            otp: $request->string('otp')->toString(),
        );

        if (!$confirmed) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Two-factor authentication enabled successfully.',
        ]);
    }

    public function disableTwoFactor(DisableTwoFactorRequest $request): JsonResponse
    {
        $disabled = $this->authService->disableTwoFactor(
            user: $request->user(),
            password: $request->string('password')->toString(),
        );

        if (!$disabled) {
            return response()->json([
                'message' => 'The provided password is incorrect.',
            ], 422);
        }

        return response()->json([
            'message' => 'Two-factor authentication disabled successfully.',
        ]);
    }

    public function verifyTwoFactor(VerifyTwoFactorRequest $request): JsonResponse
    {
        $token = $this->authService->verifyTwoFactorLogin(
            challengeToken: $request->string('challenge_token')->toString(),
            otp: $request->string('otp')->toString(),
        );

        if (!$token) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }
}
