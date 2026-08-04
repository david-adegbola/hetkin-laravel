<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Public marketing site (Stage 12) — mirrors the Next.js app's top-level
 * page.tsx files (src/app/{page,pricing,faq,how-it-works,trust,methodology,
 * beta,docs}/page.tsx and src/app/legal/*). Every route here is a closure
 * rendering a static Inertia page: nothing reads from a model, so no
 * controller is warranted — same precedent as Admin/Docs.tsx's
 * registration in routes/admin-protected.php.
 *
 * Main-domain only, same as routes/pilots.php's public surfaces — v2 never
 * proxies the marketing site to dashboard.* or admin.*. Enforced with an
 * explicit domain() constraint (rather than relying on domains.php simply
 * being required first and matching sooner) so a request to admin.{domain}
 * or dashboard.{domain} can never render marketing content, regardless of
 * route registration order elsewhere.
 */
Route::domain(config('app.domain'))->group(function () {
    Route::get('/', fn () => Inertia::render('Marketing/Home'))->name('home');
    Route::get('how-it-works', fn () => Inertia::render('Marketing/HowItWorks'))->name('how-it-works');
    Route::get('pricing', fn () => Inertia::render('Marketing/Pricing'))->name('pricing');
    Route::get('trust', fn () => Inertia::render('Marketing/Trust'))->name('trust');
    Route::get('faq', fn () => Inertia::render('Marketing/Faq'))->name('faq');
    Route::get('methodology', fn () => Inertia::render('Marketing/Methodology'))->name('methodology');

    // Intentionally unlisted investor deck. It has no navigation or sitemap
    // entry, and renders without the public marketing chrome.
    Route::get('pitch-deck', fn () => Inertia::render('PitchDeck'))->name('pitch-deck');

    // Unlisted (not linked from SiteHeader/SiteFooter nav, noindex) — see
    // each page's own docblock.
    Route::get('beta', fn () => Inertia::render('Marketing/Beta'))->name('beta');
    Route::get('docs', fn () => Inertia::render('Marketing/Docs'))->name('docs');

    Route::prefix('legal')->name('legal.')->group(function () {
        Route::get('privacy', fn () => Inertia::render('Marketing/Legal/Privacy'))->name('privacy');
        Route::get('terms', fn () => Inertia::render('Marketing/Legal/Terms'))->name('terms');
        Route::get('cookies', fn () => Inertia::render('Marketing/Legal/Cookies'))->name('cookies');
    });
});

// The local server is normally opened at 127.0.0.1 while APP_DOMAIN defaults
// to localhost. Keep the production deck main-domain-only, but make the same
// unlisted route reachable at the loopback address during local development.
if (app()->environment('local')) {
    Route::domain('127.0.0.1')
        ->get('pitch-deck', fn () => Inertia::render('PitchDeck'))
        ->name('pitch-deck.local');
}
