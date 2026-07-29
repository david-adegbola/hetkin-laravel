<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Models\AdminUser;

/**
 * See MIGRATION_PLAN.md ADR-14 and its Stage 13 follow-up. Confirms
 * App\Http\Middleware\PreventBackHistoryCache is scoped correctly:
 * present on every authenticated route group (settings, portal, admin),
 * absent from public pages — a global middleware would fail the last two
 * assertions below.
 */
function assertNoStoreHeaders($response): void
{
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
}

test('an authenticated settings response carries no-store cache headers', function () {
    confirmedPasswordUser();

    assertNoStoreHeaders($this->get(route('settings.security')));
});

test('an authenticated portal response carries no-store cache headers', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    assertNoStoreHeaders($this->actingAs($user, 'web')->get(route('dashboard')));
});

test('an authenticated admin response carries no-store cache headers', function () {
    $admin = AdminUser::factory()->create();

    assertNoStoreHeaders($this->actingAs($admin, 'admin')->get(route('admin.overview')));
});

test('the public sign-in page does not carry the no-store cache headers', function () {
    $response = $this->get('/sign-in');

    $response->assertHeaderMissing('Pragma');
    $cacheControl = $response->headers->get('Cache-Control');
    expect($cacheControl)->not->toContain('no-store');
});
