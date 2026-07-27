<?php

use App\Domain\Auth\Enums\AdminRole;
use App\Models\AdminUser;

test('admin login screen can be rendered', function () {
    $this->get('/admin/sign-in')->assertOk();
});

test('admin users can authenticate using the admin sign-in screen', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->post('/admin/sign-in', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin, 'admin');
    $response->assertRedirect(route('admin.pilots', absolute: false));
});

test('admin users cannot authenticate with an invalid password', function () {
    $admin = AdminUser::factory()->create();

    $this->post('/admin/sign-in', [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('admin');
});

test('deactivated admin users cannot authenticate even with the correct password', function () {
    $admin = AdminUser::factory()->inactive()->create();

    $response = $this->post('/admin/sign-in', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertGuest('admin');
    $response->assertSessionHasErrors('email');
});

test('admin users can log out', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post('/admin/logout');

    $this->assertGuest('admin');
    $response->assertRedirect(route('admin.login', absolute: false));
});

test('admin login is rate limited after 10 attempts per minute', function () {
    $admin = AdminUser::factory()->create();
    $ip = ['REMOTE_ADDR' => '10.20.30.41'];

    for ($i = 0; $i < 10; $i++) {
        $this->withServerVariables($ip)->post('/admin/sign-in', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    // The 11th attempt in the same window is throttled — matches the
    // Next.js app's Better Auth config (src/lib/auth-admin.ts: window 60s, max 10).
    $response = $this->withServerVariables($ip)->post('/admin/sign-in', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $response->assertStatus(429);
    $this->assertGuest('admin');
});

test('admin role is preserved through authentication', function () {
    $admin = AdminUser::factory()->role(AdminRole::VerificationAnalyst)->create();

    $this->post('/admin/sign-in', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    expect(auth('admin')->user()->role)->toBe(AdminRole::VerificationAnalyst);
});
