<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConfirmPasswordRequest extends FormRequest
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
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function ensurePasswordMatches(): void
    {
        if (! Hash::check($this->string('password')->toString(), Auth::guard('web')->user()->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }
    }
}
