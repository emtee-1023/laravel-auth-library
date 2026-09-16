<?php

return [
    'models' => [
        'user' => App\Models\User::class,
        'otp' => Markt\LaravelAuth\Models\Otp::class,
        'two_factor_challenge' => Markt\LaravelAuth\Models\TwoFactorChallenge::class,
        'two_factor_setting' => Markt\LaravelAuth\Models\TwoFactorSetting::class,
    ],
    'otp' => [
        'length' => 6, //digits
        'expires_in' => 5, //minutes
        'max_attempts' => 5,
    ],
    'two_factor' => [
        'enabled' => 'true',
        'challenge_expires_in' => 5, //minutes
    ],
    'rate_limits' => [
        'login' => [
            'attempts' => 5,
            'decay_seconds' => 60,
        ],

        'otp_verification' => [
            'attempts' => 5,
            'decay_seconds' => 300,
        ],

        'password_reset' => [
            'attempts' => 3,
            'decay_seconds' => 300,
        ],

        'two_factor' => [
            'attempts' => 5,
            'decay_seconds' => 300,
        ],
    ],
];
