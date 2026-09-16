<?php

namespace Markt\LaravelAuth\Enums;

enum OtpPurpose: string
{
    case Registration = 'registration';
    case PasswordReset = 'password_reset';
    case TwoFactor = 'two_factor';
}
