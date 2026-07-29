<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Models\AdminUser;

/**
 * MIGRATION_PLAN.md Stage 14. Covers the two things most likely to silently
 * break: the manifest's start_url/scope varying correctly across BOTH
 * deployment modes (real subdomain vs. main-domain path-prefix fallback —
 * App\Support\PwaSurface), and the server-side install-eligibility gate in
 * resources/views/app.blade.php — the actual regression test for "install
 * is only available after entering an authenticated portal", since hiding
 * a React button alone can't close that gap (see that view's docblock).
 */
test('portal manifest uses a bare scope on the real subdomain', function () {
    $response = $this->get('http://dashboard.localhost/manifest-portal.webmanifest');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json');
    $response->assertJson([
        'start_url' => '/',
        'scope' => '/',
        'id' => '/',
        'name' => 'Hetkin',
    ]);
});

test('portal manifest uses the /dashboard prefix on the main domain', function () {
    $response = $this->get('http://localhost/manifest-portal.webmanifest');

    $response->assertOk();
    $response->assertJson([
        'start_url' => '/dashboard',
        'scope' => '/dashboard/',
        'id' => '/dashboard/',
    ]);
});

test('admin manifest uses a bare scope on the real subdomain', function () {
    $response = $this->get('http://admin.localhost/manifest-admin.webmanifest');

    $response->assertOk();
    $response->assertJson([
        'start_url' => '/',
        'scope' => '/',
        'name' => 'Hetkin Admin',
    ]);
});

test('admin manifest uses the /admin prefix on the main domain', function () {
    $response = $this->get('http://localhost/manifest-admin.webmanifest');

    $response->assertOk();
    $response->assertJson([
        'start_url' => '/admin',
        'scope' => '/admin/',
    ]);
});

test('portal and admin manifests reference the same icon set', function () {
    $portal = $this->get('http://localhost/manifest-portal.webmanifest')->json();
    $admin = $this->get('http://localhost/manifest-admin.webmanifest')->json();

    expect($portal['icons'])->toEqual($admin['icons']);
});

test('no manifest link or Service Worker bootstrap for a signed-out portal visitor', function () {
    $response = $this->get('http://dashboard.localhost/sign-in');

    $response->assertOk();
    $response->assertDontSee('rel="manifest"', false);
    $response->assertDontSee('window.__PWA__', false);
});

test('no manifest link for the marketing site, signed in or not', function () {
    $response = $this->get('http://localhost/');

    $response->assertOk();
    $response->assertDontSee('rel="manifest"', false);
    $response->assertDontSee('window.__PWA__', false);
});

test('manifest link and Service Worker bootstrap appear for an authenticated portal visitor', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertOk();
    $response->assertSee('rel="manifest" href="/manifest-portal.webmanifest"', false);
    $response->assertSee('window.__PWA__', false);
    $response->assertSee('&quot;surface&quot;:&quot;portal&quot;', false);
});

test('manifest link and Service Worker bootstrap appear for an authenticated admin visitor', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.overview'));

    $response->assertOk();
    $response->assertSee('rel="manifest" href="/manifest-admin.webmanifest"', false);
    $response->assertSee('&quot;surface&quot;:&quot;admin&quot;', false);
});
