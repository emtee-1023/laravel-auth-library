<?php

namespace Markt\LaravelAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Markt\LaravelAuth\Http\Requests\LoginRequest;
use Markt\LaravelAuth\Http\Requests\RegisterRequest;
use Markt\LaravelAuth\Http\Requests\VerifyPhoneRequest;
use Markt\LaravelAuth\Services\AuthService;

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

    public function verifyPhone(
        VerifyPhoneRequest $request
    ): JsonResponse {
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
        $token = $this->authService->login(
            phoneNumber: $request->string('phone_number')->toString(),
            password: $request->string('password')->toString(),
        );

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token->plainTextToken,
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
}
