<?php

use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SessionController;
use App\Http\Middleware\PreventBackHistoryCache;
use Illuminate\Support\Facades\Route;
use Inertia\EncryptHistoryMiddleware;

/**
 * Customer realm, Account Security module. Every route here requires a
 * confirmed password (`password.confirm`) on top of `auth:web` — a
 * re-auth checkpoint before sensitive account changes, per the brief.
 * `PreventBackHistoryCache` is listed explicitly rather than inherited from
 * a global group; see that middleware's docblock for why authenticated-only
 * scoping has to be deliberate, not blanket. `EncryptHistoryMiddleware`
 * (Post-Stage-14 ADR) is the same pairing routes/portal.php uses — see its
 * docblock for why this is a distinct mechanism from PreventBackHistoryCache,
 * not a duplicate of it.
 */
Route::middleware(['auth:web', 'auth.session', PreventBackHistoryCache::class, EncryptHistoryMiddleware::class])
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('security', [SecurityController::class, 'show'])->name('security');

        Route::middleware('password.confirm')->group(function () {
            Route::put('security/password', [PasswordController::class, 'update'])->name('security.password');
            Route::delete('security/sessions/other', [SessionController::class, 'destroyOthers'])->name('security.sessions.destroy-others');
            Route::delete('security/sessions/{session}', [SessionController::class, 'destroy'])->name('security.sessions.destroy');
        });
    });
