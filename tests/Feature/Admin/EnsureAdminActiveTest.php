<?php

use App\Models\AdminUser;

test('an active admin can reach a protected admin route', function () {
    $admin = AdminUser::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get('/admin/pilots');

    $response->assertOk();
});

test('a deactivated admin is rejected and logged out', function () {
    $admin = AdminUser::factory()->inactive()->create();

    $response = $this->actingAs($admin, 'admin')->get('/admin/pilots');

    $response->assertForbidden();
    $this->assertGuest('admin');
});

test('a deactivated admin can still log out', function () {
    $admin = AdminUser::factory()->inactive()->create();

    $response = $this->actingAs($admin, 'admin')->post('/admin/logout');

    $response->assertRedirect(route('admin.login', absolute: false));
    $this->assertGuest('admin');
});
