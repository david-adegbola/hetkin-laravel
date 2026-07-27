<?php

namespace App\Http\Requests\Admin\Auth;

use App\Models\AdminUser;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Admin-realm login. Rate limiting (10 attempts/minute — tighter than the
 * customer realm's 20, deliberately: this realm holds cross-tenant access)
 * is applied via the `throttle` middleware on the route, matching Better
 * Auth's `rateLimit: { window: 60, max: 10 }` (src/lib/auth-admin.ts).
 *
 * Checks `active` explicitly rather than relying on Auth::attempt() alone:
 * a deactivated AdminUser must not be able to sign in even with a correct
 * password (src/lib/admin-session.ts's requireAdminSession() enforces this
 * on every subsequent request too — Stage 9 ports that check for
 * already-authenticated admin sessions; this is the login-time half of the
 * same rule).
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
        $adminUser = AdminUser::where('email', $this->string('email'))->first();

        if (! $adminUser || ! Hash::check($this->string('password'), $adminUser->password)) {
            throw ValidationException::withMessages([
                'email' => 'Sign-in failed.',
            ]);
        }

        if (! $adminUser->active) {
            throw ValidationException::withMessages([
                'email' => 'This admin account has been deactivated. Contact another admin.',
            ]);
        }

        Auth::guard('admin')->login($adminUser);
    }
}
