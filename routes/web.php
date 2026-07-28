<?php

use Illuminate\Support\Facades\Route;

// TODO(Stage 12 — Marketing site): the real homepage. Deliberately not
// stubbed with placeholder content here — see MIGRATION_PLAN.md's staging
// rationale. '/' is intentionally unrouted until that stage.

// Customer portal — see routes/portal.php's own docblock for why these
// definitions are shared with routes/domains.php's dashboard.* group
// rather than duplicated.
Route::prefix('dashboard')->group(require __DIR__.'/portal.php');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/pilots.php';
require __DIR__.'/domains.php';
