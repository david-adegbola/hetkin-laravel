<?php

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The riskiest piece of custom infrastructure in Stage 1
 * (MIGRATION_PLAN.md ADR-4/ADR-12): App\Http\Middleware\UseSessionRealm
 * swaps session config per route group so the admin realm gets a
 * physically separate cookie and database table from the customer realm.
 * A bug here could leak session state across the boundary the Next.js
 * app's two separate Better Auth instances enforced structurally — these
 * tests exercise the real HTTP kernel + middleware pipeline end to end,
 * not just the guard logic in isolation.
 *
 * The test suite's default SESSION_DRIVER is 'array' (phpunit.xml, for
 * speed) — these tests override to 'database' specifically, since the
 * behavior under test IS "which database table does the session land in."
 */
beforeEach(function () {
    config(['session.driver' => 'database']);
});

test('customer login writes to the sessions table, not admin_sessions', function () {
    $user = User::factory()->create();

    $this->post('/sign-in', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard', absolute: false));

    expect(DB::table('sessions')->count())->toBe(1);
    expect(DB::table('admin_sessions')->count())->toBe(0);
});

test('admin login writes to the admin_sessions table, not sessions', function () {
    $admin = AdminUser::factory()->create();

    $this->post('/admin/sign-in', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('admin.pilots', absolute: false));

    expect(DB::table('admin_sessions')->count())->toBe(1);
    expect(DB::table('sessions')->count())->toBe(0);
});

/*
 * Deliberately two separate tests, each issuing exactly one HTTP call, not
 * one test issuing both — see this file's own note above on why: Laravel's
 * SessionManager caches its resolved driver instance (Store + cookie name
 * baked in at creation) for the lifetime of the container, and PHPUnit/Pest
 * boot ONE container per test METHOD. A single test making a customer POST
 * then an admin POST would reuse the customer request's cached Store on
 * the second call and see the customer cookie name on both — a test-harness
 * artifact, not a production bug: every real HTTP request under standard
 * php-fpm gets a genuinely fresh container (MIGRATION_PLAN.md ADR-12), so
 * this caching never spans two real requests there.
 */
test('customer session uses the customer cookie name', function () {
    $user = User::factory()->create();

    $response = $this->post('/sign-in', ['email' => $user->email, 'password' => 'password']);

    $cookieNames = collect($response->headers->getCookies())->map->getName();
    expect($cookieNames)->toContain('hetkin_customer_session');
    expect($cookieNames)->not->toContain('hetkin_admin_session');
});

test('admin session uses the admin cookie name', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->post('/admin/sign-in', ['email' => $admin->email, 'password' => 'password']);

    $cookieNames = collect($response->headers->getCookies())->map->getName();
    expect($cookieNames)->toContain('hetkin_admin_session');
    expect($cookieNames)->not->toContain('hetkin_customer_session');
});

test('being authenticated as a customer does not satisfy the admin guard', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web');

    expect(auth('web')->check())->toBeTrue();
    expect(auth('admin')->check())->toBeFalse();
});

test('being authenticated as an admin does not satisfy the customer guard', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin');

    expect(auth('admin')->check())->toBeTrue();
    expect(auth('web')->check())->toBeFalse();
});

test('a customer-only session cannot reach the admin console', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->get('/admin/pilots')
        ->assertRedirect(route('admin.login', absolute: false));
});

test('an admin-only session cannot reach the customer portal', function () {
    $admin = AdminUser::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('/dashboard')
        ->assertRedirect(route('login', absolute: false));
});
