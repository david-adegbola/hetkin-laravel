<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// TODO(Stage 12 — Marketing site): the real homepage. Deliberately not
// stubbed with placeholder content here — see MIGRATION_PLAN.md's staging
// rationale. '/' is intentionally unrouted until that stage.

Route::middleware('auth:web')->group(function () {
    // TODO(Stage 5 — Scheduling/Portal shell): replace with the real
    // customer portal. Placeholder only proves the customer guard end to
    // end (MIGRATION_PLAN.md ADR-10).
    Route::get('/dashboard', fn () => Inertia::render('Portal/DashboardPlaceholder'))
        ->name('dashboard');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/domains.php';
