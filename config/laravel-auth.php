<?php

return [
    'models' => [
        'user' => App\Models\User::class,
    ],
    'otp' => [
        'length' => 6, //digits
        'expires_in' => 5, //minutes
        'max_attempts' => 5,
    ],
];
