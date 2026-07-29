<?php

use App\Http\Controllers\Portal\BillingController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DataController;
use App\Http\Controllers\Portal\RecommendationController;
use App\Http\Controllers\Portal\ReportController;
use App\Http\Controllers\Portal\ScheduleController;
use App\Http\Controllers\Portal\SupportController;
use App\Http\Controllers\Portal\TeamController;
use App\Http\Middleware\PreventBackHistoryCache;
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
 * v2's (portal)/layout.tsx gating every page under it the same way. Every
 * page from v2's full sidebar (Dashboard/Schedule/Data/Reports/Billing/
 * Team/Support) now has a real route here — Team and Support were the
 * last two, restored per MIGRATION_PLAN.md's post-Stage-12 ADR after
 * Stage 5's ADR-33 deliberately deferred them.
 *
 * `PreventBackHistoryCache` (ADR-14) was scoped only to routes/settings.php
 * at launch — its own docblock always said the portal shell should adopt
 * it too once Stage 5 built it, but that step was never actually taken.
 * Added here in Stage 13 (MIGRATION_PLAN.md's technical-modernization
 * ADR) as the security fix that closes that gap: this is exactly the
 * sensitive, session-scoped content bfcache restoration was meant to
 * protect.
 */
return function (): void {
    Route::middleware(['auth:web', 'organization.context', PreventBackHistoryCache::class])->group(function () {
        Route::get('/', [DashboardController::class, 'show'])->name('dashboard');

        Route::get('data', [DataController::class, 'index'])->name('data');
        Route::get('data/upload', [DataController::class, 'create'])->name('data.upload');
        Route::post('data/upload', [DataController::class, 'store'])->name('data.upload.store');

        Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule');
        Route::post('schedule', [ScheduleController::class, 'store'])->name('schedule.store');
        Route::post('schedule/optimize', [ScheduleController::class, 'optimize'])->name('schedule.optimize');
        Route::post('schedule/apply-decisions', [ScheduleController::class, 'applyDecisions'])->name('schedule.apply-decisions');
        Route::get('schedule/history', [ScheduleController::class, 'history'])->name('schedule.history');

        Route::post('recommendations/{recommendation}/decide', [RecommendationController::class, 'decide'])
            ->name('recommendations.decide');

        Route::get('reports', [ReportController::class, 'index'])->name('reports');
        Route::post('reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
        Route::get('reports/{assessment}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{assessment}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

        Route::get('billing', [BillingController::class, 'index'])->name('billing');

        Route::get('team', [TeamController::class, 'index'])->name('team');

        Route::get('support', [SupportController::class, 'index'])->name('support');
    });
};
