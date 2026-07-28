<?php

use App\Models\User;
use App\Notifications\PasswordResetCompleted;
use App\Notifications\PasswordResetRequested;
use Illuminate\Auth\Notifications\ResetPassword as LaravelDefaultResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('forgot password screen can be rendered', function () {
    $this->get('/forgot-password')->assertOk();
});

test('a reset link is emailed for a real account', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');

    Notification::assertSentTo($user, PasswordResetRequested::class);
    // Never Laravel's stock notification — see App\Models\User::sendPasswordResetNotification().
    Notification::assertNotSentTo($user, LaravelDefaultResetPassword::class);
});

test('the response is identical whether or not the email exists — no account enumeration', function () {
    Notification::fake();
    $user = User::factory()->create();

    $realEmailResponse = $this->post('/forgot-password', ['email' => $user->email]);
    $fakeEmailResponse = $this->post('/forgot-password', ['email' => 'nobody-here@example.test']);

    expect($realEmailResponse->status())->toBe($fakeEmailResponse->status());
    expect(session('status'))->not->toBeNull();
    // No notification at all for the non-existent account — nothing to
    // attribute an audit entry or email to.
    Notification::assertSentToTimes($user, PasswordResetRequested::class, 1);
});

test('the generic success status reaches the forgot-password page after submitting', function () {
    // Regression test: PasswordResetLinkController::store()'s
    // back()->with('status', ...) only flashes the message into the
    // session — create() has to explicitly forward session('status') into
    // Inertia props on the next render, or the generic "we've sent a
    // link" confirmation the spec requires is silently dropped and the
    // user gets no feedback at all. assertSessionHas('status') alone
    // (used elsewhere in this file) can't catch that gap since it never
    // inspects what the following page actually renders.
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    $this->get('/forgot-password')
        ->assertInertia(fn ($page) => $page
            ->component('Auth/ForgotPassword')
            ->where('status', fn ($status) => is_string($status) && str_contains($status, 'reset link')));
});

test('forgot-password requests are rate limited per IP', function () {
    $ip = ['REMOTE_ADDR' => '10.20.30.50'];

    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables($ip)->post('/forgot-password', ['email' => "user{$i}@example.test"]);
    }

    $response = $this->withServerVariables($ip)->post('/forgot-password', ['email' => 'one-more@example.test']);

    $response->assertStatus(429);
});

test('reset screen can be rendered with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, PasswordResetRequested::class, function ($notification) {
        $this->get($notification->resetUrl)->assertOk();

        return true;
    });
});

test('a password can be reset with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create(['password_changed_at' => null]);
    $token = Password::broker('users')->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    $user->refresh();
    expect(Hash::check(strong_password(), $user->password))->toBeTrue();
    expect($user->password_changed_at)->not->toBeNull();

    Notification::assertSentTo($user, PasswordResetCompleted::class);
});

test('resetting a password does not automatically log the user in', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $this->assertGuest('web');
});

test('resetting a password invalidates every existing session for that user', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'stale-session-id',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('{}'),
        'last_activity' => time(),
    ]);

    $token = Password::broker('users')->createToken($user);
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('an invalid token is rejected', function () {
    $user = User::factory()->create();

    $response = $this->from('/reset-password/bogus-token')->post('/reset-password', [
        'token' => 'bogus-token',
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

test('an expired token is rejected', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    // Broker config: 60-minute expiry (config/auth.php passwords.users.expire).
    $this->travel(61)->minutes();

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertSessionHasErrors('email');
});

test('a weak password is rejected on reset', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short1!',
        'password_confirmation' => 'short1!',
    ]);

    $response->assertSessionHasErrors('password');
});

test('mismatched password confirmation is rejected on reset', function () {
    $user = User::factory()->create();
    $token = Password::broker('users')->createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => strong_password(),
        'password_confirmation' => 'DifferentPassw0rd#2026',
    ]);

    $response->assertSessionHasErrors('password');
});
