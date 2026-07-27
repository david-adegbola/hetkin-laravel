<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Customer-realm login. Rate limiting (20 attempts/minute) is applied at
 * the route level via Laravel's `throttle` middleware, matching the
 * Next.js app's Better Auth `rateLimit: { window: 60, max: 20 }`
 * (src/lib/auth.ts) — an IP-scoped fixed window on the endpoint itself,
 * not a per-account limiter, so it belongs on the route, not duplicated
 * here per-request.
 *
 * No `minPasswordLength` check here — see MIGRATION_PLAN.md ADR-6: the
 * only place a customer password is ever set is the invitation-acceptance
 * flow (a later stage), so login only ever verifies a password that
 * already satisfies that policy.
 */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => 'Sign-in failed.',
            ]);
        }
    }
}
