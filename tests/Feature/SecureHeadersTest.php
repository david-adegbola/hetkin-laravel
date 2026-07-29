<?php

/**
 * See MIGRATION_PLAN.md's Stage 13 security review. Global — unlike
 * PreventBackHistoryCache (ADR-14), these headers apply to every response,
 * public pages included.
 */
test('every response carries the baseline security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});
