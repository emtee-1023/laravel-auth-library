<?php

use Illuminate\Support\Facades\Route;
use Markt\LaravelAuth\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone'])->middleware('throttle:laravel-auth-otp');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:laravel-auth-login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword',])->middleware('throttle:laravel-auth-password-reset');
    Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:laravel-auth-otp-resend');
    Route::post('/reset-password', [AuthController::class, 'resetPassword',])->middleware('throttle:laravel-auth-otp');
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor',])->middleware('throttle:laravel-auth-two-factor'); //does not require sanctum since the user doesn't have an access token yet

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor',]);
        Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor',])->middleware('throttle:laravel-auth-two-factor');
        Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor',]);
    });
});
