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
    Route::get('{invitation}', [InvitationController::class, 'show'])
        ->middleware('throttle:60,1')
        ->name('invite.show');

    // The `invitation-accept` limiter (App\Providers\AppServiceProvider) is
    // the real fix here, not this GET's throttle above: store() checks the
    // submitted password against an existing user's real password hash on
    // its signIn-fallback path (InvitationController's docblock), making
    // this the one unauthenticated write in the app that was a password
    // oracle with no throttle at all until Stage 15's rate-limiting review.
    Route::post('{invitation}', [InvitationController::class, 'store'])
        ->middleware('throttle:invitation-accept')
        ->name('invite.accept');
});
