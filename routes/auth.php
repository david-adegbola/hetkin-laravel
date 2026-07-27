<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/**
 * Customer realm. No register/forgot-password/reset-password/verify-email
 * routes — v2 has none of these (MIGRATION_PLAN.md ADR-6).
 *
 * Path is `/sign-in`, not Breeze's conventional `/login` — matches v2's
 * actual URL (src/app/sign-in/page.tsx) exactly, per the mission's URL
 * preservation requirement. Route NAME stays 'login' (Laravel/Breeze
 * convention other code — redirectGuestsTo, etc. — expects), only the path
 * changed.
 *
 * Rate limit (20/min) matches the Next.js app's Better Auth config
 * (src/lib/auth.ts's `rateLimit: { window: 60, max: 20 }`).
 */
Route::middleware('guest')->group(function () {
    Route::get('sign-in', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('sign-in', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:20,1');
});

Route::middleware('auth:web')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
