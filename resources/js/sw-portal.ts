/// <reference lib="webworker" />

// Entry point for the customer portal's Service Worker (MIGRATION_PLAN.md
// Stage 14) — sets which surface this worker belongs to, then pulls in the
// shared logic. Kept deliberately tiny and near-identical to sw-admin.ts;
// the only difference between the two installed apps' Service Workers is
// this one constant, which is exactly the point — each portal gets its own
// Cache Storage namespace (see sw-core.ts's CACHE_NAME) and its own
// independent install/update lifecycle even though the underlying caching
// logic is shared.

var SURFACE: 'portal' | 'admin' = 'portal';

importScripts('/service-worker/cache-policy.js', '/service-worker/sw-core.js');
