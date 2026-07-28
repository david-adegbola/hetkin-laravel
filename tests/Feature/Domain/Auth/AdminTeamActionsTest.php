<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Auth\Actions\ChangeAdminRoleAction;
use App\Domain\Auth\Actions\CreateAdminUserAction;
use App\Domain\Auth\Actions\DeactivateAdminUserAction;
use App\Domain\Auth\Actions\ReactivateAdminUserAction;
use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Auth\Exceptions\AdminTeamActionException;
use App\Models\AdminUser;
use App\Notifications\AdminAccountCreated;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

// --- CreateAdminUserAction ---------------------------------------------------

test('creates an active admin user with the given role and a usable temp password', function () {
    Notification::fake();
    $creator = AdminUser::factory()->create();

    $result = app(CreateAdminUserAction::class)->execute($creator, ' Maria Support ', ' Maria@Example.test ', 'SUPPORT');

    expect($result->adminUser->name)->toBe('Maria Support');
    expect($result->adminUser->email)->toBe('maria@example.test');
    expect($result->adminUser->role)->toBe(AdminRole::Support);
    expect($result->adminUser->active)->toBeTrue();
    expect(strlen($result->tempPassword))->toBe(32);
    expect(Hash::check($result->tempPassword, $result->adminUser->password))->toBeTrue();
});

test('rejects a blank name', function () {
    $creator = AdminUser::factory()->create();

    expect(fn () => app(CreateAdminUserAction::class)->execute($creator, '  ', 'new@example.test', 'SUPPORT'))
        ->toThrow(AdminTeamActionException::class, 'Name is required.');
});

test('rejects an email with no @', function () {
    $creator = AdminUser::factory()->create();

    expect(fn () => app(CreateAdminUserAction::class)->execute($creator, 'New Admin', 'not-an-email', 'SUPPORT'))
        ->toThrow(AdminTeamActionException::class, 'A valid email is required.');
});

test('rejects an invalid role on create', function () {
    $creator = AdminUser::factory()->create();

    expect(fn () => app(CreateAdminUserAction::class)->execute($creator, 'New Admin', 'new@example.test', 'BOGUS'))
        ->toThrow(AdminTeamActionException::class, 'Invalid role.');
});

test('rejects a duplicate email', function () {
    $creator = AdminUser::factory()->create();
    AdminUser::factory()->create(['email' => 'taken@example.test']);

    expect(fn () => app(CreateAdminUserAction::class)->execute($creator, 'New Admin', 'taken@example.test', 'SUPPORT'))
        ->toThrow(AdminTeamActionException::class, 'already exists');
});

test('sends an account-created email without the password, and writes an audit entry without the password', function () {
    Notification::fake();
    $creator = AdminUser::factory()->create(['name' => 'Root Admin']);

    $result = app(CreateAdminUserAction::class)->execute($creator, 'New Admin', 'new@example.test', 'SUPPORT');

    Notification::assertSentTo($result->adminUser, AdminAccountCreated::class);
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('admin_user.created');
    expect($entry->metadata)->toBe(['email' => 'new@example.test', 'role' => 'SUPPORT']);
    expect(json_encode($entry->metadata))->not->toContain($result->tempPassword);
});

// --- ChangeAdminRoleAction ---------------------------------------------------

test('changes the target role and writes an audit log entry', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->role(AdminRole::Support)->create();

    $updated = app(ChangeAdminRoleAction::class)->execute($target, $actor, 'AUDITOR');

    expect($updated->role)->toBe(AdminRole::Auditor);
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('admin_user.role_changed');
    expect($entry->metadata)->toBe(['from' => 'SUPPORT', 'to' => 'AUDITOR']);
});

test('rejects an invalid role', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->create();

    expect(fn () => app(ChangeAdminRoleAction::class)->execute($target, $actor, 'BOGUS'))
        ->toThrow(AdminTeamActionException::class, 'Invalid role.');
});

test('resubmitting the same role is a no-op with no audit entry', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->role(AdminRole::Support)->create();

    app(ChangeAdminRoleAction::class)->execute($target, $actor, 'SUPPORT');

    expect(AuditLogEntry::query()->count())->toBe(0);
});

test('allows an admin to change their own role, matching v2 (no server-side self-guard)', function () {
    $actor = AdminUser::factory()->role(AdminRole::Admin)->create();

    $updated = app(ChangeAdminRoleAction::class)->execute($actor, $actor, 'SUPPORT');

    expect($updated->role)->toBe(AdminRole::Support);
});

// --- DeactivateAdminUserAction -----------------------------------------------

test('deactivates the target and writes an audit log entry', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->create();

    $updated = app(DeactivateAdminUserAction::class)->execute($target, $actor);

    expect($updated->active)->toBeFalse();
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('admin_user.deactivated');
    expect($entry->metadata)->toBeNull();
});

test('an admin cannot deactivate their own account', function () {
    $actor = AdminUser::factory()->create();

    expect(fn () => app(DeactivateAdminUserAction::class)->execute($actor, $actor))
        ->toThrow(AdminTeamActionException::class, "You can't deactivate your own account.");
    expect($actor->refresh()->active)->toBeTrue();
});

// --- ReactivateAdminUserAction ------------------------------------------------

test('reactivates the target and writes an audit log entry', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->inactive()->create();

    $updated = app(ReactivateAdminUserAction::class)->execute($target, $actor);

    expect($updated->active)->toBeTrue();
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('admin_user.reactivated');
});

test('reactivating an already-active admin still writes an audit entry, matching v2', function () {
    $actor = AdminUser::factory()->create();
    $target = AdminUser::factory()->create(['active' => true]);

    app(ReactivateAdminUserAction::class)->execute($target, $actor);

    expect(AuditLogEntry::query()->where('action', 'admin_user.reactivated')->count())->toBe(1);
});
