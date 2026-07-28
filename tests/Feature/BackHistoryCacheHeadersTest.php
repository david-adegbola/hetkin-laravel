<?php

/**
 * See MIGRATION_PLAN.md ADR-14. Confirms App\Http\Middleware\
 * PreventBackHistoryCache is scoped correctly: present on authenticated
 * settings routes, absent from the public sign-in page — a global
 * middleware would fail the second half of this file.
 */
test('an authenticated settings response carries no-store cache headers', function () {
    confirmedPasswordUser();

    $response = $this->get(route('settings.security'));

    // Not an exact-string match: Symfony's ResponseHeaderBag alphabetizes
    // Cache-Control directives when it serializes the header, regardless
    // of the order App\Http\Middleware\PreventBackHistoryCache set them in.
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('must-revalidate');
    expect($cacheControl)->toContain('private');
    $response->assertHeader('Pragma', 'no-cache');
    $response->assertHeader('Expires', '0');
});

test('the public sign-in page does not carry the no-store cache headers', function () {
    $response = $this->get('/sign-in');

    $response->assertHeaderMissing('Pragma');
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->not->toContain('no-store');
});
