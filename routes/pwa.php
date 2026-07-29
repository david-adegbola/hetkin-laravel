<?php

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

/**
 * MIGRATION_PLAN.md Stage 14 — manifest + Service Worker routes for the two
 * installable portals (customer, admin). Deliberately registered with NO
 * domain() constraint, same reasoning as routes/settings.php: a single route
 * needs to answer correctly whether it's reached via dashboard.{domain} or
 * {domain}/dashboard (App\Support\PwaSurface resolves which, per-request).
 * The marketing site gets neither a manifest nor a Service Worker route —
 * there is nothing here for it to register.
 */
Route::get('manifest-portal.webmanifest', [PwaController::class, 'manifest'])
    ->defaults('surface', 'portal')
    ->name('pwa.manifest.portal');

Route::get('manifest-admin.webmanifest', [PwaController::class, 'manifest'])
    ->defaults('surface', 'admin')
    ->name('pwa.manifest.admin');

Route::get('sw-portal.js', [PwaController::class, 'serviceWorker'])
    ->defaults('surface', 'portal')
    ->name('pwa.sw.portal');

Route::get('sw-admin.js', [PwaController::class, 'serviceWorker'])
    ->defaults('surface', 'admin')
    ->name('pwa.sw.admin');
