<?php

use App\Domain\Auth\Enums\AdminRole;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Hash;

test('creates the first admin with the flags given', function () {
    $this->artisan('admin:create-first', [
        '--name' => 'Jane Doe',
        '--email' => 'Jane@Hetkin.app',
        '--password' => 'a-Very-Str0ng-Passw0rd!2026',
    ])->assertSuccessful();

    $admin = AdminUser::where('email', 'jane@hetkin.app')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Jane Doe');
    expect($admin->role)->toBe(AdminRole::Admin);
    expect($admin->active)->toBeTrue();
    expect(Hash::check('a-Very-Str0ng-Passw0rd!2026', $admin->password))->toBeTrue();
});

test('refuses when an admin account already exists', function () {
    AdminUser::factory()->create();

    $this->artisan('admin:create-first', [
        '--name' => 'Someone Else',
        '--email' => 'someone@hetkin.app',
        '--password' => 'another-Str0ng-Passw0rd!2026',
    ])->assertSuccessful();

    expect(AdminUser::count())->toBe(1);
    expect(AdminUser::where('email', 'someone@hetkin.app')->exists())->toBeFalse();
});

test('rejects a weak password', function () {
    $this->artisan('admin:create-first', [
        '--name' => 'Jane Doe',
        '--email' => 'jane@hetkin.app',
        '--password' => 'weak',
    ])->assertFailed();

    expect(AdminUser::count())->toBe(0);
});
