<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\PilotApplicationController;
use Illuminate\Support\Facades\Route;

/**
 * Authenticated-admin route DEFINITIONS, shared between two mount points —
 * same dual-registration pattern as routes/portal.php (MIGRATION_PLAN.md
 * ADR-21/ADR-34), extracted now that this group has grown past the single
 * pilots-placeholder route routes/admin.php and routes/domains.php used to
 * duplicate inline:
 *
 *   routes/admin.php:   Route::prefix('admin')->name('admin.')
 *                        ->group(require this file)
 *                        -> "/admin/pilots", ...
 *                        names: admin.logout, admin.pilots, ...
 *   routes/domains.php: Route::domain('admin.'.domain)->name('admin.subdomain.')
 *                        ->group(require this file)
 *                        -> bare "/pilots", ...
 *                        names: admin.subdomain.logout, admin.subdomain.pilots, ...
 *
 * Requiring this file evaluates and returns the closure below fresh each
 * time — safe to `require` (not `require_once`) from both mount points.
 * As later stages add real admin console pages (organizations, baselines,
 * verification, invoices, team, audit log, jobs — Stage 9), add them here
 * once, not to both call sites separately.
 */
return function (): void {
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        // Stage 8's pilot review queue — the full admin console shell
        // (org/baseline/verification/invoice/team/audit-log/jobs pages and
        // their shared nav) is still Stage 9; this route and
        // Admin/Pilots.tsx's minimal AdminLayout are the "honest subset"
        // until then (MIGRATION_PLAN.md ADR-33's pattern, applied to the
        // admin realm for the first time).
        Route::middleware('admin.role:ADMIN,SUPPORT')->group(function () {
            Route::get('pilots', [PilotApplicationController::class, 'index'])->name('pilots');
            Route::post('pilots/{pilotApplication}/approve', [PilotApplicationController::class, 'approve'])
                ->name('pilots.approve');
            Route::post('pilots/{pilotApplication}/reject', [PilotApplicationController::class, 'reject'])
                ->name('pilots.reject');
        });
    });
};
