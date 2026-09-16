<?php

namespace Markt\LaravelAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'otp' => ['required', 'digits:' . config('laravel-auth.otp.length'),],
        ];
    }
}
