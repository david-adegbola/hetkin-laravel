<?php

/**
 * MIGRATION_PLAN.md Stage 14. Depends on `npm run build:sw` having already
 * produced public/sw-portal.js / public/sw-admin.js — same dependency the
 * rest of this suite already has on `npm run build` for Vite's compiled
 * assets, just for a second, separate compile step (see package.json).
 */
test('portal Service Worker serves as JS with no-cache, no matter the host', function () {
    $subdomain = $this->get('http://dashboard.localhost/sw-portal.js');
    $mainDomain = $this->get('http://localhost/sw-portal.js');

    foreach ([$subdomain, $mainDomain] as $response) {
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/javascript');
        // Symfony's Response::prepare() appends ", private" once a session
        // cookie is involved (every route in the `web` group starts one) —
        // same reasoning tests/Feature/BackHistoryCacheHeadersTest.php
        // already works around with a substring check rather than an exact
        // match, since the directive we actually care about is still there.
        expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    }
});

test('admin Service Worker serves as JS with no-cache', function () {
    $response = $this->get('http://admin.localhost/sw-admin.js');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/javascript');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});

test('portal and admin Service Workers are distinct files with independent cache names', function () {
    $portal = $this->get('http://localhost/sw-portal.js')->getContent();
    $admin = $this->get('http://localhost/sw-admin.js')->getContent();

    expect($portal)->toContain("var SURFACE = 'portal'");
    expect($admin)->toContain("var SURFACE = 'admin'");
});
