<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\PilotApplicationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * The pilot funnel's two public-facing surfaces — mirrors the Next.js
 * app's src/app/pilot-program/apply and src/app/invite/[invitationId]
 * route groups. Both are fully unauthenticated and main-domain only (no
 * dashboard-subdomain or admin-subdomain equivalent — v2 doesn't proxy
 * either of these to a subdomain either, see routes/domains.php's own
 * SUBDOMAIN_RULES comment).
 *
 * The `pilot-application` rate limiter is registered in
 * App\Providers\AppServiceProvider (5 submissions per IP per 10 minutes,
 * matching v2's in-memory limiter exactly).
 */
Route::prefix('pilot-program')->group(function () {
    Route::get('/', fn () => Inertia::render('PilotProgram/Index'))->name('pilot-program.index');
    Route::get('apply', [PilotApplicationController::class, 'create'])->name('pilot-program.apply');
    Route::post('apply', [PilotApplicationController::class, 'store'])
        ->middleware('throttle:pilot-application')
        ->name('pilot-program.apply.store');
});

Route::prefix('invite')->group(function () {
    Route::get('{invitation}', [InvitationController::class, 'show'])->name('invite.show');
    Route::post('{invitation}', [InvitationController::class, 'store'])->name('invite.accept');
});
