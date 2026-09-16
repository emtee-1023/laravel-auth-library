<?php

use Illuminate\Support\Facades\Route;
use Markt\LaravelAuth\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword',]);
    Route::post('/reset-password', [AuthController::class, 'resetPassword',]);
    Route::post('/2fa/verify', [AuthController::class, 'verifyTwoFactor',]); //does not require sanctum since the user doesn't have an access token yet

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/2fa/enable', [AuthController::class, 'enableTwoFactor',]);
        Route::post('/2fa/confirm', [AuthController::class, 'confirmTwoFactor',]);
        Route::post('/2fa/disable', [AuthController::class, 'disableTwoFactor',]);
    });
});
