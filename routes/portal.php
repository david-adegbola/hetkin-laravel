<?php

use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DataController;
use App\Http\Controllers\Portal\ScheduleController;
use Illuminate\Support\Facades\Route;

/**
 * Customer portal route DEFINITIONS, shared between two mount points
 * (MIGRATION_PLAN.md ADR-21's dual-registration pattern, extended here via
 * a shared closure rather than writing every route twice — safe for
 * portal routes specifically because, unlike sign-in, none of them need
 * different treatment on the main domain vs. the dashboard subdomain; see
 * ADR-33 for why this doesn't fight ADR-21's own reasoning for rejecting
 * a fully generic rewrite):
 *
 *   routes/web.php:      Route::prefix('dashboard')->group(require this file)
 *                         -> "/dashboard", "/dashboard/data", ...
 *                         names: dashboard, data, data.upload, schedule, ...
 *   routes/domains.php:  Route::domain('dashboard.'.domain)->name('portal.')
 *                         ->group(require this file)
 *                         -> bare "/", "/data", ...
 *                         names: portal.dashboard, portal.data, ...
 *
 * Requiring this file evaluates and returns the closure below fresh each
 * time (it declares no named symbol, just an anonymous function) — safe to
 * `require` (not `require_once`) from both mount points.
 *
 * Every route requires an authenticated customer AND a resolved
 * organization (App\Http\Middleware\ResolveOrganizationContext) — matches
 * v2's (portal)/layout.tsx gating every page under it the same way. As
 * later stages add real portal pages (billing, reports, team, support),
 * add them here once, not to both routes/web.php and routes/domains.php
 * separately.
 */
return function (): void {
    Route::middleware(['auth:web', 'organization.context'])->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');

        Route::get('data', [DataController::class, 'index'])->name('data');
        Route::get('data/upload', [DataController::class, 'create'])->name('data.upload');
        Route::post('data/upload', [DataController::class, 'store'])->name('data.upload.store');

        Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule');
        Route::post('schedule', [ScheduleController::class, 'store'])->name('schedule.store');
        Route::get('schedule/history', [ScheduleController::class, 'history'])->name('schedule.history');
    });
};
