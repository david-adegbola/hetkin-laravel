import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import vm from 'node:vm';

/**
 * Unit tests for the one function responsible for "protected content is
 * never cached" (MIGRATION_PLAN.md Stage 14). Run via Node's native
 * TypeScript support (`npm run test:js`) — no test framework dependency
 * added; this repo is otherwise Pest-only and has no JS test infra, so this
 * intentionally stays as small and dependency-free as possible rather than
 * pulling in vitest/jest for one function.
 *
 * Loads the actual COMPILED output (public/service-worker/cache-policy.js)
 * via node:vm rather than importing the .ts source directly: cache-policy.ts
 * is deliberately a plain global-scope script with no `export` (see its own
 * docblock for why — TypeScript's module:none target otherwise emits a
 * broken CommonJS wrapper for a browser context), so there's no ES binding
 * to import in the first place. This also means the test exercises the
 * exact artifact the Service Worker actually loads, not a parallel
 * hand-written re-implementation that could quietly drift from it.
 * `npm run test:js` runs the tsconfig.sw-portal.json compile first to
 * guarantee this file exists and is current.
 */
const compiledPath = new URL(
    '../../../public/service-worker/cache-policy.js',
    import.meta.url,
);
const source = readFileSync(compiledPath, 'utf8');
const sandbox: { shouldCache?: (pathname: string) => boolean } = {};
vm.runInNewContext(source, sandbox);
const shouldCache = sandbox.shouldCache!;

test('caches Vite build assets', () => {
    assert.equal(shouldCache('/build/assets/app-abc123.js'), true);
    assert.equal(shouldCache('/build/assets/app-abc123.css'), true);
    assert.equal(shouldCache('/build/assets/geist-sans-Bj2R.woff2'), true);
});

test('caches icons', () => {
    assert.equal(shouldCache('/icons/icon-192.png'), true);
});

test('caches the two manifests', () => {
    assert.equal(shouldCache('/manifest-portal.webmanifest'), true);
    assert.equal(shouldCache('/manifest-admin.webmanifest'), true);
});

test('never caches portal or admin page paths', () => {
    assert.equal(shouldCache('/dashboard/schedule'), false);
    assert.equal(shouldCache('/admin/organizations'), false);
    assert.equal(shouldCache('/schedule'), false);
});

test('never caches API-shaped or root paths', () => {
    assert.equal(shouldCache('/'), false);
    assert.equal(shouldCache('/sign-in'), false);
    assert.equal(shouldCache('/data/upload'), false);
});

test('does not match on a substring, only a real prefix', () => {
    assert.equal(shouldCache('/build-assets-lookalike/x.js'), false);
    assert.equal(shouldCache('/icons-fake/x.png'), false);
});
