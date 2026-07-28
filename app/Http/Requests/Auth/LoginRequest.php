<?php

namespace App\Http\Requests\Auth;

use App\Domain\Auth\Services\LoginRateLimiter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Customer-realm login. Two independent rate-limiting layers, deliberately
 * not merged into one:
 *  - Route-level `throttle:20,1` (routes/auth.php) — IP-scoped fixed
 *    window, matching the Next.js app's Better Auth `rateLimit: { window:
 *    60, max: 20 }` (src/lib/auth.ts).
 *  - LoginRateLimiter below — per-account (email+IP) progressive lockout,
 *    the Account Security module's "progressive delays / temporary
 *    lockout" requirement, which a flat window doesn't provide.
 *
 * No `minPasswordLength` check here — see MIGRATION_PLAN.md ADR-6: the
 * only place a customer password is ever set is the invitation-acceptance
 * flow (a later stage) or this module's reset/change-password flows, all
 * of which already enforce Password::defaults(). Login only ever verifies
 * a password that already satisfies that policy.
 */
class LoginRequest extends FormRequest
{
    public function __construct(private readonly LoginRateLimiter $rateLimiter)
    {
        parent::__construct();
    }

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
        $key = $this->rateLimiter->key($this->string('email')->toString(), $this->ip());

        $this->ensureIsNotLockedOut($key);

        if (! Auth::guard('web')->attempt($this->only('email', 'password'))) {
            $this->rateLimiter->recordFailedAttempt($key);

            throw ValidationException::withMessages([
                'email' => 'Sign-in failed.',
            ]);
        }

        $this->rateLimiter->clear($key);
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotLockedOut(string $key): void
    {
        if (! $this->rateLimiter->tooManyAttempts($key)) {
            return;
        }

        $seconds = $this->rateLimiter->availableInSeconds($key);
        $minutes = (int) ceil($seconds / 60);

        throw ValidationException::withMessages([
            'email' => "Too many failed sign-in attempts. Try again in {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').'.',
        ]);
    }
}
