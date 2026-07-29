/**
 * The one function that decides "protected content is never cached"
 * (MIGRATION_PLAN.md Stage 14). Deliberately kept pure and dependency-free
 * — no reference to `self`/`caches`/any Service Worker global.
 *
 * Deliberately NOT an ES module (no `export`) even though that makes it
 * slightly more awkward to unit-test (see cache-policy.test.ts, which loads
 * the compiled output via node:vm instead of a plain import): TypeScript's
 * `module: "none"` target still emits a CommonJS `exports.shouldCache = …`
 * wrapper for any file that uses `export`, referencing a global `exports`
 * object that doesn't exist in a Service Worker — confirmed by compiling
 * this file both ways and inspecting the output. A plain global function
 * declaration, with no import/export anywhere, is the only form that
 * compiles to something a classic script can actually load via
 * importScripts() without throwing ReferenceError on the first line.
 *
 * Allow-list only — everything NOT matched here must fall through
 * untouched to the network. In particular this must never match anything
 * under a portal/admin page path, any Inertia XHR, or any API route: those
 * responses carry real, per-user data and must never enter Cache Storage.
 */
function shouldCache(pathname: string): boolean {
    if (pathname.startsWith('/build/assets/')) {
        // Vite's hashed, immutable JS/CSS/font output (Laravel Vite plugin
        // fingerprints the filename on every change) — safe to cache
        // indefinitely per version, since a new deploy's asset URLs never
        // collide with the previous deploy's.
        return true;
    }

    if (pathname.startsWith('/icons/')) {
        return true;
    }

    if (
        pathname === '/manifest-portal.webmanifest' ||
        pathname === '/manifest-admin.webmanifest'
    ) {
        return true;
    }

    return false;
}
