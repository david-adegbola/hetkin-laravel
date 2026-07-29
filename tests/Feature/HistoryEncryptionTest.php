<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Models\AdminUser;
use Illuminate\Testing\TestResponse;

/**
 * Post-Stage-14 ADR. Complements tests/Feature/BackHistoryCacheHeadersTest.php
 * — that test covers response HTTP headers (PreventBackHistoryCache);
 * this one covers a genuinely separate mechanism (Inertia's own
 * client-side history-state cache, restored on browser back/forward via
 * `popstate` independent of any HTTP header). See routes/portal.php's
 * EncryptHistoryMiddleware docblock for the full explanation of why both
 * are needed.
 *
 * Reads the page object straight out of the initial HTML response's
 * `data-page="…"` attribute rather than sending `X-Inertia: true` — a
 * bare version-less Inertia XHR request 409s (Inertia's own
 * version-mismatch conflict response), and matching the client's real
 * asset version just to read two boolean flags would be more machinery
 * than the assertion is worth.
 */
function inertiaPageData(TestResponse $response): array
{
    $response->assertOk();

    preg_match('/data-page="([^"]+)"/', $response->getContent(), $matches);
    expect($matches)->toHaveCount(2, 'no data-page attribute found in response HTML');

    $decoded = html_entity_decode($matches[1], ENT_QUOTES);

    return json_decode($decoded, true);
}

test('portal responses carry encryptHistory: true', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $page = inertiaPageData(
        $this->actingAs($user, 'web')->get(route('dashboard')),
    );

    expect($page['encryptHistory'])->toBeTrue();
});

test('admin responses carry encryptHistory: true', function () {
    $admin = AdminUser::factory()->create();

    $page = inertiaPageData(
        $this->actingAs($admin, 'admin')->get(route('admin.overview')),
    );

    expect($page['encryptHistory'])->toBeTrue();
});

test('settings responses carry encryptHistory: true', function () {
    $user = confirmedPasswordUser();

    $page = inertiaPageData(
        $this->get(route('settings.security')),
    );

    expect($page['encryptHistory'])->toBeTrue();
});

test('customer logout flags the next response to clear history', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $this->actingAs($user, 'web')->post(route('logout'));

    // clearHistory is a one-time session flag (Inertia\ResponseFactory
    // pulls it from the session) — surfaces on the very next Inertia
    // response, which after logout is the sign-in page.
    $page = inertiaPageData($this->get('/sign-in'));

    expect($page['clearHistory'])->toBeTrue();
});

test('admin logout flags the next response to clear history', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $page = inertiaPageData($this->get(route('admin.login')));

    expect($page['clearHistory'])->toBeTrue();
});
