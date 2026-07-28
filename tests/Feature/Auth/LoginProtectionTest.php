<?php

use App\Domain\Auth\Services\LoginRateLimiter;
use App\Models\User;

/**
 * Exercises App\Domain\Auth\Services\LoginRateLimiter's escalating lockout,
 * distinct from tests/Feature/Auth/CustomerAuthenticationTest.php's flat
 * throttle:20,1 coverage — see MIGRATION_PLAN.md ADR-16 for why these are
 * two separate layers. Each test uses a unique IP so the route-level
 * throttle:20,1 (20/min) never interferes with attempt counts below that.
 */
test('an account is locked out after 5 failed attempts', function () {
    $user = User::factory()->create();
    $ip = ['REMOTE_ADDR' => '10.30.0.1'];

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables($ip)->post('/sign-in', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // Correct password, but the account is now locked out regardless.
    $response = $this->withServerVariables($ip)->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('lockout duration escalates with more failed attempts', function () {
    // Exercises App\Domain\Auth\Services\LoginRateLimiter directly, not
    // through HTTP: once locked out, LoginRequest rejects further attempts
    // before they ever reach recordFailedAttempt() (correctly — a locked
    // account's credential check never runs), so simulating "10 failed
    // HTTP attempts in a row" doesn't actually record 10 attempts. Real
    // escalation happens across separate lockout windows over real time,
    // which this drives directly instead of via $this->travel() gymnastics.
    $rateLimiter = app(LoginRateLimiter::class);
    $key = $rateLimiter->key('escalation-test@example.test', '10.30.0.2');

    for ($i = 0; $i < 4; $i++) {
        $rateLimiter->recordFailedAttempt($key);
    }
    expect($rateLimiter->tooManyAttempts($key))->toBeFalse();

    $rateLimiter->recordFailedAttempt($key); // 5th — crosses into the 1-minute tier
    expect($rateLimiter->availableInSeconds($key))->toBeLessThanOrEqual(60);
    expect($rateLimiter->availableInSeconds($key))->toBeGreaterThan(0);

    for ($i = 0; $i < 5; $i++) {
        $rateLimiter->recordFailedAttempt($key);
    }
    expect($rateLimiter->availableInSeconds($key))->toBeLessThanOrEqual(300);
    expect($rateLimiter->availableInSeconds($key))->toBeGreaterThan(60); // 10th — crosses into the 5-minute tier
});

test('a successful login clears the failed-attempt count', function () {
    $user = User::factory()->create();
    $ip = ['REMOTE_ADDR' => '10.30.0.3'];

    for ($i = 0; $i < 4; $i++) {
        $this->withServerVariables($ip)->post('/sign-in', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // 4 failures is below the 5-attempt lockout threshold, so this succeeds...
    $this->withServerVariables($ip)->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($user, 'web');

    // ...and the count resets: 4 more failures afterward still shouldn't lock out.
    auth('web')->logout();
    for ($i = 0; $i < 4; $i++) {
        $this->withServerVariables($ip)->post('/sign-in', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }
    $response = $this->withServerVariables($ip)->post('/sign-in', [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticatedAs($user, 'web');
});
