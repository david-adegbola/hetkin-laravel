<?php

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

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

/*
 * Regression coverage for a real bug: routes/web.php's `/dashboard` prefix
 * and routes/admin.php's `/admin` prefix originally carried no domain()
 * constraint at all, so they matched ANY host — including the wrong one.
 * A browser with a valid customer session visiting admin.{domain} rendered
 * the customer portal there (wrong page, and — since SESSION_DOMAIN has a
 * leading dot — the real customer session cookie was honored on that host
 * too). Each prefix is now domain()-constrained to the bare app domain;
 * dashboard.{domain}/admin.{domain} are served exclusively by their own
 * bare-path registrations in routes/domains.php.
 */
test('the main-domain dashboard prefix does not match the admin subdomain', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')
        ->get('http://admin.localhost/dashboard')
        ->assertNotFound();
});

test('the main-domain admin prefix does not match the dashboard subdomain', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('http://dashboard.localhost/admin/pilots')
        ->assertNotFound();
});

/*
 * Regression coverage for the second half of the same bug report: because
 * routes/pilots.php's `invite` group also carried no domain() constraint,
 * `route('invite.show', ...)` — called while handling an admin-console
 * request — fell back to the CURRENT request's host to build the absolute
 * URL, producing invite links on admin.{domain} instead of the bare
 * domain. Approving a pilot application from the admin subdomain must
 * still produce a bare-domain invite link.
 */
test('an invite link generated from the admin subdomain still points at the bare domain', function () {
    Notification::fake();
    $admin = AdminUser::factory()->create();
    $application = PilotApplication::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post('http://admin.localhost/pilots/'.$application->id.'/approve')
        ->assertRedirect();

    $invitation = Invitation::withoutGlobalScope(OrganizationScope::class)->first();

    $response = $this->actingAs($admin, 'admin')->get('http://admin.localhost/pilots');

    $response->assertInertia(fn ($page) => $page
        ->where('decided.0.invite_url', route('invite.show', ['invitation' => $invitation->id])));

    expect($invitation)->not->toBeNull();
    expect(parse_url(route('invite.show', ['invitation' => $invitation->id]), PHP_URL_HOST))
        ->not->toContain('admin.');
});
