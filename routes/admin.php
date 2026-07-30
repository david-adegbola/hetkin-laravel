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
 *
 * The protected group below is domain()-constrained to the bare app
 * domain: this is the main-domain `/admin` path-prefix mount point
 * specifically (routes/domains.php already covers admin.{domain} on its
 * own bare paths, with the same explicit `:admin` middleware). Without
 * that constraint the prefix has no host restriction at all and would
 * ALSO match dashboard.{domain}, rendering admin console content there.
 *
 * The guest sign-in routes are deliberately left OUT of that constraint,
 * matching routes/auth.php's precedent for routes with no per-host
 * equivalent: resources/js/Pages/Admin/Auth/Login.tsx always posts to
 * `route('admin.login.attempt')` regardless of which host rendered the
 * page (there is no host-aware branching there, same as the customer
 * Login.tsx always posting to plain `route('login')`). Domain-constraining
 * this route would have made that call always resolve to the bare app
 * domain even when submitted from admin.{domain}, breaking sign-in there.
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

        Route::domain(config('app.domain'))->group(function () {
            Route::group([], require __DIR__.'/admin-protected.php');
        });
    });
