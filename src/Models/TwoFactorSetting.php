<?php

namespace Markt\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorSetting extends Model
{
    protected $table = 'two_factor_settings';

    protected $fillable = [
        'user_id',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
