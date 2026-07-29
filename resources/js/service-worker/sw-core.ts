/// <reference lib="webworker" />
/// <reference path="./service-worker.d.ts" />

// Compiled as a classic (non-module) Service Worker script — see
// tsconfig.sw-portal.json / tsconfig.sw-admin.json's docblocks for why.
// `shouldCache` and `SURFACE` are runtime globals brought in via
// `importScripts()` by sw-portal.ts / sw-admin.ts before this file loads,
// not TS imports — classic SW scripts can't use ES modules (Firefox
// doesn't support module Service Workers at all; Safari only gained
// support in 16.4). These `declare`s are type-only and erased on emit.
declare function shouldCache(pathname: string): boolean;
// `var`, not `const`/`let`: sw-portal.ts / sw-admin.ts each declare their
// own real `var SURFACE = '...'` before importScripts()-ing this file in —
// `var` is the one declaration form TypeScript (like real JS) allows to
// redeclare across files sharing one global scope, which is exactly what's
// happening here (a `const`/`let` "forward declaration" would conflict
// with the entry file's real declaration instead of matching it).
declare var SURFACE: 'portal' | 'admin';

// TypeScript's built-in "webworker" lib types the ambient `self` as the
// generic WorkerGlobalScope, not the Service-Worker-specific
// ServiceWorkerGlobalScope declared in service-worker.d.ts — casting once
// here (rather than trying to redeclare the global `self` binding, which
// conflicts with lib's own declaration) is the standard way to get the
// narrower, correct type for everything below.
const sw = self as unknown as ServiceWorkerGlobalScope;

/**
 * Fixed per-surface cache name (MIGRATION_PLAN.md Stage 14) — deliberately
 * NOT tied to a build/deploy version string. Vite fingerprints every JS/CSS
 * asset filename by content hash, so a new deploy's URLs never collide with
 * a stale cached entry from the previous one; nothing ever serves an old
 * asset by mistake regardless of what's still sitting in Cache Storage. The
 * suffix only needs bumping if the CACHING STRATEGY itself changes (a new
 * allow-listed path shape, a different fetch strategy) — not on every
 * ordinary deploy — see docs/pwa.md.
 */
const CACHE_NAME = `hetkin-${SURFACE}-v1`;
const OFFLINE_URL = '/offline.html';

sw.addEventListener('install', (event) => {
    event.waitUntil(
        sw.caches.open(CACHE_NAME).then((cache) => cache.add(OFFLINE_URL)),
    );
    // Don't wait for old tabs to close before the new worker takes over
    // install — it still won't CONTROL any open page until it activates
    // and claims clients below, so this alone doesn't interrupt anything
    // already in progress.
    sw.skipWaiting();
});

sw.addEventListener('activate', (event) => {
    event.waitUntil(
        sw.caches
            .keys()
            .then((names) =>
                Promise.all(
                    names
                        .filter(
                            (name) =>
                                name.startsWith('hetkin-') &&
                                name !== CACHE_NAME,
                        )
                        .map((name) => sw.caches.delete(name)),
                ),
            )
            .then(() => sw.clients.claim()),
    );
});

sw.addEventListener('message', (event) => {
    // Posted by resources/js/lib/pwa.ts's applyUpdate() when the user
    // explicitly clicks "Refresh now" on the update banner — install()'s
    // sw.skipWaiting() above already covers the normal case; this handler
    // exists for the narrower "a worker is already waiting because a prior
    // page load never reloaded" case.
    if (event.data === 'SKIP_WAITING') {
        sw.skipWaiting();
    }
});

sw.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only ever intercept same-origin GET requests. Everything else (any
    // POST/PUT/DELETE form submission, any cross-origin request) passes
    // straight through untouched.
    if (
        request.method !== 'GET' ||
        new URL(request.url).origin !== sw.location.origin
    ) {
        return;
    }

    // Navigation (full-page/document) requests: network-first, falling
    // back to the cached offline shell ONLY when the network genuinely
    // fails. The real page response is NEVER put into Cache Storage here —
    // it's server-rendered Inertia HTML carrying real, per-user data.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(
                () => sw.caches.match(OFFLINE_URL) as Promise<Response>,
            ),
        );

        return;
    }

    const pathname = new URL(request.url).pathname;

    // Everything that isn't on the static-asset allow list — every Inertia
    // XHR page-visit, every /portal/*, /admin/*, or API call — is left
    // completely unintercepted: falls through to the browser's normal
    // network fetch, never touches caches.put(). This is the actual
    // enforcement of "protected content is never cached", not just a
    // comment — see cache-policy.ts's shouldCache().
    if (!shouldCache(pathname)) {
        return;
    }

    event.respondWith(
        sw.caches.open(CACHE_NAME).then(async (cache) => {
            const cached = await cache.match(request);

            if (cached) {
                return cached;
            }

            const response = await fetch(request);

            if (response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        }),
    );
});

// Push notifications: architecture only, not implemented yet (per
// MIGRATION_PLAN.md Stage 14's scope — "future push notification
// architecture", not a working feature). No subscription flow, no backend
// endpoint, no opt-in UI exist yet; these listeners are here so the wiring
// point is visible and real work can start from here later, not from
// scratch. See docs/pwa.md.
sw.addEventListener('push', () => {
    // Intentionally no-op.
});

sw.addEventListener('notificationclick', (event) => {
    event.notification.close();
});
