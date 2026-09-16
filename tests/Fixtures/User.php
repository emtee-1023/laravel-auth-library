<?php

namespace Markt\LaravelAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'phone_verified_at' => 'datetime',
    ];
}