<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Anyone may try to log in. The "api-login" rate limiter stops brute force.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // A label for the token ("iPhone 15", "Postman"), so the user can
            // see which devices are logged in and revoke one of them.
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }
}
