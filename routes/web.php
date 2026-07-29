<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Customer portal — see routes/portal.php's own docblock for why these
// definitions are shared with routes/domains.php's dashboard.* group
// rather than duplicated.
Route::prefix('dashboard')->group(require __DIR__.'/portal.php');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/pilots.php';
require __DIR__.'/domains.php';
require __DIR__.'/marketing.php';

// Mirrors the Next.js app's src/app/not-found.tsx, which Next renders
// automatically for any unmatched path. Must stay last — Route::fallback()
// only matches when nothing above it did.
Route::fallback(fn () => Inertia::render('NotFound')->toResponse(request())->setStatusCode(404));
