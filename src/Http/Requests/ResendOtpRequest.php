<?php

namespace Markt\LaravelAuth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'in:registration,password_reset,two_factor'],
            'phone_number' => ['required_unless:purpose,two_factor', 'string', 'max:20'],
            'challenge_token' => ['required_if:purpose,two_factor', 'string'],
        ];
    }
}