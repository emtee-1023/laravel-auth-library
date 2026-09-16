<?php

use Illuminate\Support\Facades\Route;
use Markt\LaravelAuth\Http\Controllers\AuthController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword',]);
    Route::post('/reset-password', [AuthController::class, 'resetPassword',]);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
    });
});
