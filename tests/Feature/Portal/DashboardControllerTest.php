<?php

test('a member sees the portal dashboard', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});

test('the dashboard is also reachable bare on the dashboard subdomain', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get('http://dashboard.localhost/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});
