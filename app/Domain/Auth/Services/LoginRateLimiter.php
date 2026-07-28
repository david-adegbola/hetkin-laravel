<?php

namespace App\Domain\Auth\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Progressive login lockout — complements, not replaces, the route-level
 * `throttle:20,1` on POST /sign-in (routes/auth.php). That route throttle
 * is a blunt per-IP cap; this is a per-account (email + IP) escalating
 * lockout, which is what "progressive delays" in the Account Security
 * brief actually means beyond a single flat window: a fixed-window
 * limiter treats attempt 5 and attempt 50 the same once both are inside
 * the window, where a real attacker keeps trying past any one window.
 *
 * Attempt count persists for 24h (long enough that an attacker can't just
 * wait out a short window and restart the tier from zero) and resets
 * immediately on a successful login. Lockout duration is a pure function
 * of that count — same escalation curve GitHub/most enterprise SaaS use:
 *   5–9 failed attempts   → 1 minute
 *   10–14 failed attempts → 5 minutes
 *   15–19 failed attempts → 15 minutes
 *   20+ failed attempts   → 30 minutes
 *
 * Keyed by email + IP (not IP alone) — matches Fortify's own convention:
 * an attacker spraying many emails from one IP still hits the route-level
 * IP throttle; this catches someone hammering ONE account from anywhere.
 */
class LoginRateLimiter
{
    private const ATTEMPTS_TTL_SECONDS = 86400;

    public function key(string $email, string $ip): string
    {
        return Str::lower($email).'|'.$ip;
    }

    public function tooManyAttempts(string $key): bool
    {
        return Cache::has($this->lockoutCacheKey($key));
    }

    public function availableInSeconds(string $key): int
    {
        $expiresAt = Cache::get($this->lockoutCacheKey($key).':expires_at');

        return $expiresAt ? max(0, $expiresAt - time()) : 0;
    }

    public function recordFailedAttempt(string $key): void
    {
        $attemptsKey = $this->attemptsCacheKey($key);

        Cache::add($attemptsKey, 0, self::ATTEMPTS_TTL_SECONDS);
        $attempts = Cache::increment($attemptsKey);

        $decaySeconds = $this->decaySecondsFor($attempts);

        if ($decaySeconds !== null) {
            Cache::put($this->lockoutCacheKey($key), true, $decaySeconds);
            Cache::put($this->lockoutCacheKey($key).':expires_at', time() + $decaySeconds, $decaySeconds);
        }
    }

    public function clear(string $key): void
    {
        Cache::forget($this->attemptsCacheKey($key));
        Cache::forget($this->lockoutCacheKey($key));
        Cache::forget($this->lockoutCacheKey($key).':expires_at');
    }

    private function decaySecondsFor(int $attempts): ?int
    {
        return match (true) {
            $attempts >= 20 => 1800,
            $attempts >= 15 => 900,
            $attempts >= 10 => 300,
            $attempts >= 5 => 60,
            default => null,
        };
    }

    private function attemptsCacheKey(string $key): string
    {
        return "login-attempts:{$key}";
    }

    private function lockoutCacheKey(string $key): string
    {
        return "login-lockout:{$key}";
    }
}
