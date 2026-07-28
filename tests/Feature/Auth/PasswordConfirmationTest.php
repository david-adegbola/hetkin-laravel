<?php

use App\Models\User;

test('the confirm-password screen can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->get('/confirm-password')->assertOk();
});

test('a sensitive settings route redirects to confirm-password when unconfirmed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertRedirect(route('password.confirm'));
});

test('password can be confirmed with the correct password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response->assertRedirect(route('settings.security', absolute: false));
    $this->assertTrue(session('auth.password_confirmed_at') > 0);
});

test('password confirmation fails with an incorrect password', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertNull(session('auth.password_confirmed_at'));
});

test('a sensitive settings route is reachable once password is confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')->post('/confirm-password', ['password' => 'password']);

    $response = $this->put(route('settings.security.password'), [
        'current_password' => 'password',
        'password' => strong_password(),
        'password_confirmation' => strong_password(),
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors();
});
