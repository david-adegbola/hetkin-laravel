# Progressive Web App

How Hetkin's two installable apps — the customer portal and the admin
console — actually work under the hood. This document is the practical
"how it works, how to touch it" reference.

## Three surfaces, two installable identities

Hetkin has three surfaces sharing one Laravel codebase: the public
marketing site, the customer portal, and the admin console. Portal and
admin are each reachable two ways — a real subdomain
(`dashboard.{domain}`, `admin.{domain}`) or a path prefix on the main
domain (`/dashboard/*`, `/admin/*`), depending on whether `APP_DOMAIN`
subdomains are actually configured.
`App\Support\PwaSurface` is the one place that detects which surface a
request belongs to and what that surface's manifest `start_url`/`scope`
should be for either mode — every other PWA-related piece (the manifest
route, the install-eligibility gate, `HandleInertiaRequests`'s shared
`subdomain.surface` prop) reuses it rather than re-deriving the same
host/path check.

The marketing site gets neither a manifest nor a Service Worker — there's
nothing PWA-related registered for it at all, by construction, not by a
feature flag that could be left on by mistake.

## Install eligibility: computed server-side, kept correct client-side

`resources/views/app.blade.php` computes, per request:

```php
$pwaSurface = PwaSurface::current(request());        // 'marketing' | 'portal' | 'admin'
$pwaAuthenticated = /* auth('web') or auth('admin') check matching $pwaSurface */;
```

The `<link rel="manifest">` tag and a small inline bootstrap script
(`window.__PWA__ = { surface, scope }`) are **only ever emitted when both
are true**. This matters beyond just "hiding the UI": Chrome/Edge's
native install affordance (the omnibox icon, the "Install app" menu item)
is driven purely by "a manifest is linked and a Service Worker controls
this page" being true on the current page — hiding
`InstallPwaButton.tsx`'s own React button does nothing to suppress that
native icon if those two things exist on, say, an unauthenticated sign-in
page. blade's computation is what closes that gap for the very first
page load (what a crawler, or a hard reload, sees before any JS runs).

That's not the whole story, though — Inertia is an SPA, and after that
first load it never re-renders `app.blade.php`'s `<head>` again for the
rest of the browsing session. Relying on the blade-deposited value alone
was tried first and found broken during manual testing: an in-app login
(the normal case — a form submit that Inertia handles as a client-side
XHR navigation, not a full page reload) left the manifest link and
`window.__PWA__` stuck at whatever they were on the sign-in page, i.e.
absent — the browser's install affordance simply never appeared until
the visitor happened to hit reload. The mirror-image bug existed on
sign-out too: a manifest added pre-logout would still be sitting there
after an in-app sign-out.

The fix: `resources/js/lib/pwa.ts`'s `syncPwaForPage()` re-runs the same
surface/auth check on **every** Inertia navigation
(`router.on('navigate', ...)` in `app.tsx`), adding or removing the
`<link rel="manifest">` tag and registering the Service Worker
imperatively as needed. It has no independent host-detection logic of
its own to drift from `PwaSurface` — it reads the exact same
`subdomain.isDashboardHost`/`isAdminHost` booleans already shared on
every single Inertia response, just applying the trivial "subdomain →
bare scope, else → prefixed scope" mapping to them. blade's initial
computation and the JS-side ongoing sync are two expressions of the same
rule at two different points in the page lifecycle, not two independent
gates that could disagree.

## What's cached, and why nothing else is

`resources/js/service-worker/cache-policy.ts` exports one pure function,
`shouldCache(pathname)`, that allow-lists exactly three things:

- `/build/assets/*` — Vite's content-hashed, immutable JS/CSS/font output.
  Safe to cache indefinitely; a new deploy's asset URLs never collide
  with the previous deploy's, so nothing ever serves stale.
- `/icons/*` — the PWA icon set.
- The two manifest paths.

Everything else — every navigation to a real page, every Inertia XHR,
every `/portal/*`/`/admin/*`/API call — is **not intercepted at all** by
the Service Worker's `fetch` handler (`resources/js/service-worker/
sw-core.ts`); it falls straight through to the network untouched. That's
the actual enforcement of "protected content is never cached" — not a
comment, a code path that's simply never reached for anything carrying
real user data. Navigation requests get a network-first strategy with a
fallback to a static `public/offline.html` **only** on genuine network
failure — the real page response, which embeds live per-user Inertia
data server-side, is never written to Cache Storage.

The two portals' caches are fully separate (`hetkin-portal-v1` vs.
`hetkin-admin-v1`, set by `sw-portal.ts`/`sw-admin.ts` respectively) —
installing one never shares storage or update lifecycle with the other.

## Why the Service Worker is hand-rolled, plain classic scripts

No bundler, no Workbox, no `vite-plugin-pwa` — consistent with the rest
of this codebase's posture of hand-rolling rather than pulling in a
library for something this contained. Concretely:

- `resources/js/service-worker/cache-policy.ts` and `sw-core.ts` are
  plain global-scope TypeScript, compiled via two small, dedicated
  configs (`tsconfig.sw-portal.json`, `tsconfig.sw-admin.json`,
  `module: "none"`) into classic (non-module) `.js` files under `public/`.
  `sw-portal.ts`/`sw-admin.ts` load the shared logic via
  `importScripts()` at runtime, not a TS import — classic Service Worker
  scripts can't use ES modules at all (Firefox has no module-SW support;
  Safari only gained it in 16.4), so this isn't a style choice, it's a
  compatibility requirement.
- `cache-policy.ts` deliberately has **no `export` keyword**, even though
  that makes testing it slightly less direct (see below): TypeScript's
  `module: "none"` target still emits a CommonJS `exports.shouldCache = …`
  wrapper for a file that uses `export`, referencing a global `exports`
  object that doesn't exist in a Service Worker — confirmed by compiling
  it both ways during development. A plain global function is the only
  form that survives `importScripts()` without throwing
  `ReferenceError: exports is not defined` as the very first thing the
  worker does.
- Two separate tsconfig files, not one compiling both entry points
  together: `sw-portal.ts` and `sw-admin.ts` each declare a top-level
  `var SURFACE`, and since neither file is a module, a single shared
  compilation would see both simultaneously in one global scope and
  reject it as a genuine redeclaration — even though the two never
  actually coexist at runtime.
- `resources/js/service-worker/service-worker.d.ts` hand-declares the
  handful of Service-Worker-specific types (`ServiceWorkerGlobalScope`,
  `FetchEvent`, `ExtendableEvent`, `Clients`) that TypeScript's built-in
  `webworker` lib doesn't ship — a long-standing, well-known gap in
  TypeScript's standard library, not a local oversight.

## Testing

- `resources/js/service-worker/cache-policy.test.ts` — unit tests for
  `shouldCache()`, run via Node's native TypeScript support
  (`npm run test:js`), zero new test-framework dependency (this repo is
  otherwise Pest-only). It loads the **compiled** `public/service-worker/
  cache-policy.js` via `node:vm` rather than importing the `.ts` source
  directly — the source has no `export` to import (see above), and this
  way the test exercises the exact artifact the Service Worker actually
  ships, not a parallel hand-written copy that could quietly drift from
  it. `npm run test:js` runs the `build:sw` compile step first to
  guarantee the file it's testing exists and is current.
- `tests/Feature/PwaManifestTest.php` — manifest JSON content across all
  four host/path combinations, and the actual regression test for the
  install-eligibility gate: asserts the manifest link and
  `window.__PWA__` are absent for a signed-out visitor and for the
  marketing site, present only for an authenticated visitor on the
  matching surface.
- `tests/Feature/ServiceWorkerTest.php` — the `/sw-portal.js`/
  `/sw-admin.js` routes serve the right content type and
  `Cache-Control: no-cache` (explicit, not left to hosting-platform
  static-file defaults — otherwise the browser's ~24h update
  byte-comparison check can be satisfied from a stale HTTP cache instead
  of ever reaching the network).
- Everything else — the actual install flow, the offline fallback
  rendering, the update banner — needs a live browser and isn't
  PHPUnit/Node-testable: verify manually via Chrome DevTools → Application
  → Manifest / Service Workers / Cache Storage.

## Local development

The Service Worker isn't rebuilt by Vite's dev server (`npm run dev`) —
it's a separate, deliberately non-hot-reloaded compile step. After
touching anything under `resources/js/service-worker/`,
`resources/js/sw-portal.ts`, or `resources/js/sw-admin.ts`, rebuild with:

```bash
npm run build:sw
```

Then hard-reload (or unregister the old Service Worker in DevTools →
Application → Service Workers) to pick up the change — the browser's own
update lifecycle otherwise won't notice a locally-rebuilt file until its
next scheduled check.

To exercise both deployment modes locally: `dashboard.localhost:PORT` /
`admin.localhost:PORT` (real subdomains — every modern browser resolves
`*.localhost` automatically, no `/etc/hosts` edit needed) exercise
subdomain mode; `localhost:PORT/dashboard`, `localhost:PORT/admin`
exercise the path-prefix fallback.

## Versioning — deliberately NOT what drives update detection

`config('app.version')` (shared to the frontend as `appVersion`) is
**purely informational** — a "which build is this" value useful for
support/debugging, sourced from `APP_VERSION` if set, falling back to a
live `git rev-parse` only in local dev (see `config/app.php`'s docblock
for the full reasoning — an earlier draft of this feature tried to use a
version string baked in at JS build time compared against this PHP
value, and that mechanism was dropped specifically because it can
silently disagree in a real deploy: a value computed independently at
build time in CI vs. at request time on a production runtime container
that may not even have `.git` is a genuine, not hypothetical, drift risk).

**Set `APP_VERSION` explicitly in production** (e.g. from CI's commit
SHA) so this value is meaningful there — see
[DEPLOYMENT.md](DEPLOYMENT.md).

Update *detection* itself needs no version string at all: it's the
browser's own native Service Worker install/waiting lifecycle
(`resources/js/lib/pwa.ts`'s `usePwaUpdate()`), which reacts to the SW
script's bytes actually changing. `sw-core.ts`'s Cache Storage name
(`hetkin-{surface}-v1`) is similarly a fixed literal, not tied to
`APP_VERSION` — it only needs bumping if the caching *strategy* itself
changes, since Vite's content-hashed asset URLs already prevent stale
files from ever being served regardless of what's sitting in a cache.

## Push notifications — architecture only, not implemented

`sw-core.ts` has `push`/`notificationclick` event listeners, but they're
no-ops. There is no subscription flow, no backend endpoint to store a
`PushSubscription`, and no opt-in UI anywhere in the app. `.env.example`
has commented-out `VAPID_PUBLIC_KEY`/`VAPID_PRIVATE_KEY` placeholders as
the visible starting point for whoever picks this up — not a working,
just-needs-configuring feature.

## Icons

`public/icons/mark.svg` is a standalone copy of the same four `<path>`s
as `resources/js/Components/Logo.tsx`'s `LogoMark`, with a hardcoded
`#0f172a` fill (the live component uses `currentColor`, which a
rasterized PNG can't follow). If the mark ever changes, update both files
and regenerate the PNGs:

```bash
cd public/icons
magick -background none mark.svg -resize 192x192 icon-192.png
magick -background none mark.svg -resize 512x512 icon-512.png
magick -background "#f8fafc" mark.svg -resize 300x300 -gravity center -extent 512x512 icon-maskable-512.png
magick -background "#f8fafc" mark.svg -resize 113x113 -gravity center -extent 192x192 icon-maskable-192.png
```

`#f8fafc` matches both manifests' `background_color`; the maskable
variants' extra padding follows the standard ~80%-safe-zone guidance for
adaptive icon masking on Android.
