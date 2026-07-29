<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $this->get('/sign-in')->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user, 'web');
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login always lands on Dashboard, even with a stashed intended URL', function () {
    // Simulates what actually happens when a signed-out visitor hits a
    // protected page directly: Laravel's `auth` middleware (via
    // Illuminate\Routing\Redirector::guest(), triggered automatically,
    // not something this app calls itself) stashes that URL in the
    // session before bouncing to sign-in. AuthenticatedSessionController
    // deliberately uses redirect()->to(), not ->intended(), so login
    // always lands on Dashboard regardless — a deliberate product
    // decision, not an oversight.
    session(['url.intended' => '/support']);

    $user = User::factory()->create();

    $response = $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users cannot authenticate with an invalid password', function () {
    $user = User::factory()->create();

    $this->post('/sign-in', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('web');
});

test('users can log out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->post('/logout');

    $this->assertGuest('web');
    $response->assertRedirect('/');
});

test('login is rate limited after 20 attempts per minute', function () {
    $user = User::factory()->create();
    // A distinct source IP per test avoids the throttle bucket picking up
    // hits from other tests in this file that also POST /sign-in.
    $ip = ['REMOTE_ADDR' => '10.20.30.40'];

    for ($i = 0; $i < 20; $i++) {
        $this->withServerVariables($ip)->post('/sign-in', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // The 21st attempt in the same window is throttled — matches the
    // Next.js app's Better Auth config (src/lib/auth.ts: window 60s, max 20).
    $response = $this->withServerVariables($ip)->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(429);
    $this->assertGuest('web');
});
