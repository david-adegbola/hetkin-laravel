<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Middleware\UseSessionRealm;
use Illuminate\Support\Facades\Route;

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

        Route::group([], require __DIR__.'/admin-protected.php');
    });
