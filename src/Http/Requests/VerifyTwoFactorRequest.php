<?php

namespace Markt\LaravelAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string',],
            'otp' => ['required', 'digits:' . config('laravel-auth.otp.length'),],
        ];
    }
}
