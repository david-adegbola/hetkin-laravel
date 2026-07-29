/// <reference lib="webworker" />

// Admin console's Service Worker entry point — see sw-portal.ts's docblock,
// same reasoning, only SURFACE differs.

var SURFACE: 'portal' | 'admin' = 'admin';

importScripts('/service-worker/cache-policy.js', '/service-worker/sw-core.js');
