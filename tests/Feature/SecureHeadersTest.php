<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Models\AdminUser;

/**
 * See MIGRATION_PLAN.md's Stage 13 security review (the three headers
 * below) and Stage 15's dedicated Content-Security-Policy pass (this
 * class's own docblock explains why CSP was deliberately deferred out of
 * Stage 13). Global — unlike PreventBackHistoryCache (ADR-14), these
 * headers apply to every response, public pages included.
 */
test('every response carries the baseline security headers', function () {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('every response carries a Content-Security-Policy header with a fresh per-request nonce', function () {
    $first = $this->get('/');
    $second = $this->get('/');

    $firstPolicy = $first->headers->get('Content-Security-Policy');
    $secondPolicy = $second->headers->get('Content-Security-Policy');

    expect($firstPolicy)->not->toBeNull();
    expect($firstPolicy)->toContain("default-src 'self'");
    expect($firstPolicy)->toContain("object-src 'none'");
    expect($firstPolicy)->toContain("frame-ancestors 'self'");

    // Extract the nonce each response's script-src carries and confirm
    // it's actually random per-request, not a fixed placeholder — a
    // constant nonce defeats the entire point (an attacker's injected
    // <script> could just reuse it).
    preg_match("/'nonce-([^']+)'/", $firstPolicy, $firstMatch);
    preg_match("/'nonce-([^']+)'/", $secondPolicy, $secondMatch);

    expect($firstMatch)->toHaveCount(2);
    expect($secondMatch)->toHaveCount(2);
    expect($firstMatch[1])->not->toBe($secondMatch[1]);
});

test('the CSP nonce on the response header matches the nonce on the page\'s inline scripts', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $policy = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $policy, $match);
    $nonce = $match[1];

    // Every inline <script> in the rendered HTML — the Ziggy route table
    // and (on this authenticated portal page) the PWA bootstrap flag —
    // must carry this exact nonce, or the browser refuses to run it.
    $response->assertSee('nonce="'.$nonce.'"', false);
    expect(substr_count($response->getContent(), 'nonce="'.$nonce.'"'))->toBeGreaterThanOrEqual(2);
});

test('admin console responses also carry a matching CSP nonce on their inline scripts', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.overview'));

    $policy = $response->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $policy, $match);
    $nonce = $match[1];

    $response->assertSee('nonce="'.$nonce.'"', false);
});
