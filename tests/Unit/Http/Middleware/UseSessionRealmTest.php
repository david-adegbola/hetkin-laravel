<?php

use App\Http\Middleware\UseSessionRealm;
use Illuminate\Http\Request;

/*
 * Unit-level coverage for the middleware's host auto-detection, isolated
 * from the full HTTP kernel: tests/Feature/Auth/SessionRealmIsolationTest.php
 * already notes that a single test issuing two requests with different
 * hosts can't reliably observe two different resolved session stores
 * (Laravel's SessionManager caches its driver instance for the container's
 * lifetime, and Pest boots one container per test). Calling the middleware
 * directly sidesteps that entirely.
 */
test('an unparameterized realm resolves to admin on the admin host', function () {
    config(['app.domain' => 'hetkin.app']);
    $request = Request::create('http://admin.hetkin.app/dashboard');

    (new UseSessionRealm)->handle($request, fn ($req) => response(''), null);

    expect(config('session.cookie'))->toBe(config('session.admin.cookie'));
    expect(config('session.table'))->toBe(config('session.admin.table'));
});

test('an unparameterized realm resolves to customer on the main domain', function () {
    config(['app.domain' => 'hetkin.app']);
    $defaultCookie = config('session.cookie');
    $request = Request::create('http://hetkin.app/dashboard');

    (new UseSessionRealm)->handle($request, fn ($req) => response(''), null);

    expect(config('session.cookie'))->toBe($defaultCookie);
});

test('an unparameterized realm resolves to customer on the dashboard subdomain', function () {
    config(['app.domain' => 'hetkin.app']);
    $defaultCookie = config('session.cookie');
    $request = Request::create('http://dashboard.hetkin.app/');

    (new UseSessionRealm)->handle($request, fn ($req) => response(''), null);

    expect(config('session.cookie'))->toBe($defaultCookie);
});

test('an explicit admin realm parameter still wins regardless of host', function () {
    config(['app.domain' => 'hetkin.app']);
    $request = Request::create('http://hetkin.app/admin/pilots');

    (new UseSessionRealm)->handle($request, fn ($req) => response(''), 'admin');

    expect(config('session.cookie'))->toBe(config('session.admin.cookie'));
});
