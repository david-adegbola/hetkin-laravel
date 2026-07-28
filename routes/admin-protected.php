<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\BaselineController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\JobController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\PilotApplicationController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\VerificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Authenticated-admin route DEFINITIONS, shared between two mount points —
 * same dual-registration pattern as routes/portal.php (MIGRATION_PLAN.md
 * ADR-21/ADR-34):
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
 *
 * Role middleware on every mutation route matches each Action's exact v2
 * `requireAdminRole([...])` allow-list — see each controller's own
 * docblock for the source citation. Read-only index/show routes carry no
 * `admin.role` middleware at all, matching v2's "notes and read-only pages
 * have no role gate" rule (src/lib/admin-session.ts).
 */
return function (): void {
    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('logout');

        // 'admin.active' re-checks AdminUser.active on every request past
        // this point — see App\Http\Middleware\EnsureAdminActive's
        // docblock. Deliberately excludes the logout route above: a
        // deactivated admin must still be able to end their own session
        // cleanly rather than being 403'd trying to log out.
        Route::middleware('admin.active')->group(function () {
            // Deliberately NOT registered at bare '/' — on the admin
            // subdomain, bare '/' is already claimed by
            // routes/domains.php's `admin.subdomain.home` (always the
            // sign-in page, matching v2's proxy-level `rootTarget`
            // rewrite, unconditional regardless of auth state — see
            // tests/Feature/SubdomainRoutingTest.php). Registering this
            // route at '/' too would silently replace that entry in
            // Laravel's route collection (same method+URI+domain), so it
            // gets its own path instead, on both mount points.
            Route::get('overview', [DashboardController::class, 'index'])->name('overview');

            Route::middleware('admin.role:ADMIN,SUPPORT')->group(function () {
                Route::get('pilots', [PilotApplicationController::class, 'index'])->name('pilots');
                Route::post('pilots/{pilotApplication}/approve', [PilotApplicationController::class, 'approve'])
                    ->name('pilots.approve');
                Route::post('pilots/{pilotApplication}/reject', [PilotApplicationController::class, 'reject'])
                    ->name('pilots.reject');
            });

            Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations');
            Route::get('organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
            Route::post('organizations/{organization}/notes', [OrganizationController::class, 'addNote'])
                ->name('organizations.notes.store');
            Route::middleware('admin.role:ADMIN')->group(function () {
                Route::post('organizations/{organization}/status', [OrganizationController::class, 'updateStatus'])
                    ->name('organizations.status');
                Route::post('organizations/{organization}/pricing', [OrganizationController::class, 'updatePricing'])
                    ->name('organizations.pricing');
                Route::post('organizations/{organization}/erase', [OrganizationController::class, 'eraseData'])
                    ->name('organizations.erase');
            });
            Route::middleware('admin.role:ADMIN,SUPPORT')->group(function () {
                Route::post('organizations/{organization}/members/{member}/role', [OrganizationController::class, 'updateMemberRole'])
                    ->name('organizations.members.role');
                Route::delete('organizations/{organization}/members/{member}', [OrganizationController::class, 'removeMember'])
                    ->name('organizations.members.remove');
                Route::post('organizations/{organization}/invitations/{invitation}/resend', [OrganizationController::class, 'resendInvitation'])
                    ->name('organizations.invitations.resend');
            });

            Route::get('baselines', [BaselineController::class, 'index'])->name('baselines');
            Route::middleware('admin.role:ADMIN,VERIFICATION_ANALYST')->group(function () {
                Route::post('baselines/{baseline}/lock', [BaselineController::class, 'lock'])->name('baselines.lock');
            });

            Route::get('verification', [VerificationController::class, 'index'])->name('verification');
            Route::middleware('admin.role:ADMIN,VERIFICATION_ANALYST')->group(function () {
                Route::post('verification/{record}/confirm-shadow-review', [VerificationController::class, 'confirmShadowReview'])
                    ->name('verification.confirm-shadow-review');
                Route::post('verification/{record}/approve-live', [VerificationController::class, 'approveLive'])
                    ->name('verification.approve-live');
                Route::post('verification/{record}/dispute', [VerificationController::class, 'dispute'])
                    ->name('verification.dispute');
            });
            Route::middleware('admin.role:ADMIN')->group(function () {
                Route::post('verification/{record}/reopen', [VerificationController::class, 'reopen'])
                    ->name('verification.reopen');
            });

            Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices');
            Route::middleware('admin.role:ADMIN')->group(function () {
                Route::post('invoices/generate/{record}', [InvoiceController::class, 'generate'])->name('invoices.generate');
                Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue'])->name('invoices.issue');
                Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
                Route::post('invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
            });

            Route::get('team', [TeamController::class, 'index'])->name('team');
            Route::middleware('admin.role:ADMIN')->group(function () {
                Route::post('team', [TeamController::class, 'store'])->name('team.store');
                Route::post('team/{adminUser}/role', [TeamController::class, 'changeRole'])->name('team.role');
                Route::post('team/{adminUser}/deactivate', [TeamController::class, 'deactivate'])->name('team.deactivate');
                Route::post('team/{adminUser}/reactivate', [TeamController::class, 'reactivate'])->name('team.reactivate');
            });

            Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log');

            Route::get('jobs', [JobController::class, 'index'])->name('jobs');
            Route::middleware('admin.role:ADMIN')->group(function () {
                Route::post('jobs/run-verification', [JobController::class, 'triggerRunVerification'])
                    ->name('jobs.run-verification');
                Route::post('jobs/ingest-prices', [JobController::class, 'triggerIngestPrices'])
                    ->name('jobs.ingest-prices');
                Route::post('jobs/generate-alerts', [JobController::class, 'triggerGenerateAlerts'])
                    ->name('jobs.generate-alerts');
                Route::post('jobs/backfill-prices', [JobController::class, 'triggerBackfillPrices'])
                    ->name('jobs.backfill-prices');
            });

            // Static content — see resources/js/Pages/Admin/Docs.tsx's
            // docblock. No controller needed: nothing here reads from a
            // model or takes a role-gated action.
            Route::get('docs', fn () => Inertia::render('Admin/Docs'))->name('docs');
        });
    });
};
