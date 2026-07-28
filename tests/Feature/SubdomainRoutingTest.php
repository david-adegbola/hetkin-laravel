<?php

use App\Models\AdminUser;
use App\Models\User;

/**
 * Stage 2 — dashboard.* / admin.* subdomain route groups
 * (routes/domains.php, MIGRATION_PLAN.md ADR-21), the Laravel-native
 * equivalent of the Next.js app's src/proxy.ts rewrite. Every test hits a
 * full URL with an explicit host (http://dashboard.localhost/..., not a
 * bare path) — Laravel's test client parses the host from the URL and
 * matches it against Route::domain() the same way a real request would,
 * confirmed by `php artisan route:list` showing these routes registered
 * under "dashboard.localhost" / "admin.localhost" (config('app.domain')
 * defaults to "localhost" — see config/app.php and .env).
 */
test('the customer sign-in page renders bare on the dashboard subdomain', function () {
    $this->get('http://dashboard.localhost/sign-in')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('an authenticated customer sees the portal root bare on the dashboard subdomain', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')
        ->get('http://dashboard.localhost/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});

test('a guest hitting the dashboard subdomain root is sent to that subdomain\'s own sign-in, not the main domain\'s', function () {
    $this->get('http://dashboard.localhost/')
        ->assertRedirect('http://dashboard.localhost/sign-in');
});

test('logging in on the dashboard subdomain lands back on that subdomain, not the main-domain prefixed path', function () {
    $user = User::factory()->create();

    $this->post('http://dashboard.localhost/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('http://dashboard.localhost');
});

test('the admin sign-in page renders bare on the admin subdomain root, matching v2\'s rootTarget', function () {
    $this->get('http://admin.localhost/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Auth/Login'));
});

test('the admin sign-in page also renders at the bare /sign-in path on the admin subdomain', function () {
    $this->get('http://admin.localhost/sign-in')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Auth/Login'));
});

test('an authenticated admin sees the pilots review queue bare on the admin subdomain', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('http://admin.localhost/pilots')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Admin/Pilots'));
});

test('a guest hitting the admin subdomain pilots page is sent to that subdomain\'s own sign-in', function () {
    $this->get('http://admin.localhost/pilots')
        ->assertRedirect('http://admin.localhost/sign-in');
});

test('logging in on the admin subdomain lands back on that subdomain, not the main-domain prefixed path', function () {
    $admin = AdminUser::factory()->create();

    $this->post('http://admin.localhost/sign-in', [
        'email' => $admin->email,
        'password' => 'password',
    ])->assertRedirect('http://admin.localhost/pilots');
});

test('the main-domain prefixed routes are untouched by the subdomain groups', function () {
    ['user' => $user] = portalUser();
    $admin = AdminUser::factory()->create();

    $this->get('/sign-in')->assertOk();
    $this->actingAs($user, 'web')->get('/dashboard')->assertOk();
    $this->get('/admin/sign-in')->assertOk();
    $this->actingAs($admin, 'admin')->get('/admin/pilots')->assertOk();
});

test('a customer session from the dashboard subdomain does not satisfy the admin subdomain guard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get('http://admin.localhost/pilots')
        ->assertRedirect('http://admin.localhost/sign-in');
});

// Two separate tests, each issuing exactly one HTTP call, not one test
// issuing both — see tests/Feature/Auth/SessionRealmIsolationTest.php's
// header comment / MIGRATION_PLAN.md ADR-19 for why a single test chaining
// two $this->get() calls with different hosts is unreliable here.
test('the shared subdomain prop flags the dashboard subdomain', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')
        ->get('http://dashboard.localhost/')
        ->assertInertia(fn ($page) => $page
            ->where('subdomain.isDashboardHost', true)
            ->where('subdomain.isAdminHost', false));
});

test('the shared subdomain prop does not flag the main domain', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->where('subdomain.isDashboardHost', false)
            ->where('subdomain.isAdminHost', false));
});
