<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Middleware\UseSessionRealm;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * dashboard.* / admin.* subdomain route groups (MIGRATION_PLAN.md ADR-21) —
 * the Laravel-native equivalent of the Next.js app's src/proxy.ts rewrite.
 * Reuses the exact same controllers/closures as the main-domain routes in
 * routes/web.php / routes/admin.php; this file only adds the BARE-PATH
 * registrations so dashboard.{domain} and admin.{domain} resolve without
 * their main-domain path prefix, mirroring src/proxy.ts's SUBDOMAIN_RULES:
 *
 *   dashboard.* + "/"        -> portal root (bare — main domain's
 *                                equivalent is the prefixed "/dashboard")
 *   dashboard.* + "/sign-in" -> customer sign-in (bare — v2's explicit
 *                                passthrough entry: sign-in lives outside
 *                                the portal's own route group even on the
 *                                main domain, see routes/auth.php)
 *   admin.*     + "/"        -> admin sign-in content directly (v2's
 *                                `rootTarget`: admin has no landing page at
 *                                bare "/admin", so root renders sign-in
 *                                rather than 404ing)
 *   admin.*     + "/sign-in" -> admin sign-in (bare)
 *   admin.*     + "/pilots"  -> admin pilots placeholder (bare)
 *
 * Action routes (logout, password reset, settings mutations, ...) are
 * deliberately NOT re-registered here: none of them carry a domain()
 * constraint in their own files, so they already match a request on ANY
 * host Laravel receives — re-registering them would be pure duplication
 * with no behavior change. Mirrors v2's own posture (src/proxy.ts leaves
 * /api/* alone entirely; only user-facing PAGE routes need the rewrite).
 *
 * As later stages add real portal/admin pages, extend these two groups the
 * same way: one more Route:: call per page, reusing that page's existing
 * controller — see ADR-21 for why explicit dual registration (vs. a
 * generic path-rewriting middleware) was chosen.
 */
Route::domain('dashboard.'.config('app.domain'))->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('sign-in', [AuthenticatedSessionController::class, 'create'])
            ->name('portal.login');

        Route::post('sign-in', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('portal.login.attempt');
    });

    Route::middleware('auth:web')
        ->get('/', fn () => Inertia::render('Portal/DashboardPlaceholder'))
        ->name('portal.home');
});

Route::domain('admin.'.config('app.domain'))
    ->middleware([UseSessionRealm::class.':admin', 'web'])
    ->group(function () {
        Route::middleware('guest:admin')->group(function () {
            Route::get('sign-in', [AdminAuthenticatedSessionController::class, 'create'])
                ->name('admin.subdomain.login');

            Route::post('sign-in', [AdminAuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('admin.subdomain.login.attempt');

            Route::get('/', [AdminAuthenticatedSessionController::class, 'create'])
                ->name('admin.subdomain.home');
        });

        Route::middleware('auth:admin')
            ->get('pilots', fn () => Inertia::render('Admin/PilotsPlaceholder'))
            ->name('admin.subdomain.pilots');
    });
