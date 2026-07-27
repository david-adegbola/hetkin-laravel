<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Middleware\UseSessionRealm;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Admin realm. Every route here carries `UseSessionRealm::class.':admin'`
 * FIRST, then 'web' — that ordering is load-bearing, not stylistic, see
 * App\Http\Middleware\UseSessionRealm's docblock. Mirrors the Next.js app's
 * src/app/admin/** route group, minus the two subdomain-rewrite entry
 * points (`admin.hetkin.app`) deferred to Stage 2 (MIGRATION_PLAN.md ADR-9).
 *
 * Rate limit (10/min, tighter than the customer realm's 20) matches Better
 * Auth's admin config (src/lib/auth-admin.ts's `rateLimit: { window: 60,
 * max: 10 }`).
 */
Route::prefix('admin')
    ->middleware([UseSessionRealm::class.':admin', 'web'])
    ->name('admin.')
    ->group(function () {
        Route::middleware('guest:admin')->group(function () {
            Route::get('sign-in', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

            Route::post('sign-in', [AuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('login.attempt');
        });

        Route::middleware('auth:admin')->group(function () {
            Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');

            // TODO(Stage 9 — Admin console): replace with the real pilots
            // review page. Placeholder only proves the admin guard +
            // session-realm isolation end to end (MIGRATION_PLAN.md ADR-10).
            Route::get('pilots', fn () => Inertia::render('Admin/PilotsPlaceholder'))
                ->name('pilots');
        });
    });
