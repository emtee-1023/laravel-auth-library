<?php

namespace Markt\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorChallenge extends Model
{
    protected $table = 'two_factor_challenges';

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
