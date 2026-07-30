<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Customer portal — see routes/portal.php's own docblock for why these
// definitions are shared with routes/domains.php's dashboard.* group
// rather than duplicated.
//
// domain() constrained to the bare app domain: this is the main-domain
// path-prefix mount point specifically (routes/domains.php already covers
// dashboard.{domain} on its own bare paths). Without this constraint the
// prefix has no host restriction at all and matches admin.{domain} too,
// rendering customer portal content there — the isolation that matters
// (session data) is now also enforced at the middleware layer regardless
// of host (see UseSessionRealm), but this stops the wrong page/branding
// from rendering on the admin subdomain in the first place.
Route::domain(config('app.domain'))->prefix('dashboard')->group(require __DIR__.'/portal.php');

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/pilots.php';
require __DIR__.'/domains.php';
require __DIR__.'/marketing.php';
require __DIR__.'/pwa.php';

// Mirrors the Next.js app's src/app/not-found.tsx, which Next renders
// automatically for any unmatched path. Must stay last — Route::fallback()
// only matches when nothing above it did.
Route::fallback(fn () => Inertia::render('NotFound')->toResponse(request())->setStatusCode(404));
