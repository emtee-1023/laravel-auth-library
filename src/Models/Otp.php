<?php

namespace Markt\LaravelAuth\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $table = 'otps';

    protected $fillable = [
        'phone_number',
        'otp_hash',
        'expires_at',
        'attempts',
    ];
}
