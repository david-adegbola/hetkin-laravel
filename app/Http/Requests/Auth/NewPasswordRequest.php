<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class NewPasswordRequest extends FormRequest
{
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            // Password::defaults() (AppServiceProvider) supplies the
            // strength policy — one source of truth shared with the
            // change-password Form Request, not duplicated here.
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ];
    }
}
