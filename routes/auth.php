<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Route;

/**
 * Customer realm. Forgot-password / reset-password / password-confirmation
 * are new functionality added by the Account Security module — v2 itself
 * has none of these (MIGRATION_PLAN.md ADR-6), but this module was
 * explicitly commissioned to add real ones (MIGRATION_PLAN.md ADR-13). No
 * register or verify-email routes: still no self-serve signup or email
 * verification anywhere in this app.
 *
 * Path is `/sign-in`, not Breeze's conventional `/login` — matches v2's
 * actual URL (src/app/sign-in/page.tsx) exactly, per the mission's URL
 * preservation requirement. Route NAME stays 'login' (Laravel/Breeze
 * convention other code — redirectGuestsTo, etc. — expects), only the path
 * changed.
 *
 * Rate limit (20/min on sign-in) matches the Next.js app's Better Auth
 * config (src/lib/auth.ts's `rateLimit: { window: 60, max: 20 }`).
 * Forgot-password gets its own, tighter limiter — see AppServiceProvider's
 * RateLimiter::for('password-reset-link', ...) registration — since abuse
 * there means mail-bombing an arbitrary inbox, not brute-forcing a login.
 */
Route::middleware('guest')->group(function () {
    Route::get('sign-in', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('sign-in', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset-link')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset-link')
        ->name('password.store');
});

Route::middleware('auth:web')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->middleware('throttle:6,1');
});
