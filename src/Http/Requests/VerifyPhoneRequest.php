<?php

namespace Markt\LaravelAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['required', 'string', 'max:20'],
            'otp' => ['required', 'digits:' . config('laravel-auth.otp.length'),],
        ];
    }
}
