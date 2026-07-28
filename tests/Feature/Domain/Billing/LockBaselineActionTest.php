<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\LockBaselineAction;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;

test('locks a draft baseline', function () {
    $organization = Organization::factory()->create();
    $adminUser = AdminUser::factory()->create();
    $baseline = Baseline::factory()->for($organization, 'organization')->create();

    $locked = app(LockBaselineAction::class)->execute($baseline->id, $adminUser);

    expect($locked->status)->toBe(BaselineStatus::Locked);
    expect($locked->locked_at)->not->toBeNull();
    expect($locked->locked_by_admin_user_id)->toBe($adminUser->id);
});

test('locking an already-locked baseline is a no-op', function () {
    $organization = Organization::factory()->create();
    $firstAdmin = AdminUser::factory()->create();
    $secondAdmin = AdminUser::factory()->create();
    $baseline = Baseline::factory()->locked()->for($organization, 'organization')->create([
        'locked_by_admin_user_id' => $firstAdmin->id,
    ]);

    $result = app(LockBaselineAction::class)->execute($baseline->id, $secondAdmin);

    expect($result->locked_by_admin_user_id)->toBe($firstAdmin->id);
});

test('works across organizations without a bound CurrentOrganization', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $adminUser = AdminUser::factory()->create();
    $baselineA = Baseline::factory()->for($organizationA, 'organization')->create();
    $baselineB = Baseline::factory()->for($organizationB, 'organization')->create();

    app(LockBaselineAction::class)->execute($baselineA->id, $adminUser);
    app(LockBaselineAction::class)->execute($baselineB->id, $adminUser);

    expect(Baseline::withoutGlobalScope(OrganizationScope::class)
        ->where('status', BaselineStatus::Locked)->count())->toBe(2);
});

test('writes an audit log entry attributed to the admin', function () {
    $organization = Organization::factory()->create();
    $adminUser = AdminUser::factory()->create();
    $baseline = Baseline::factory()->for($organization, 'organization')->create();

    app(LockBaselineAction::class)->execute($baseline->id, $adminUser);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('baseline.locked');
    expect($entry->actor_type->value)->toBe('ADMIN_USER');
    expect($entry->actor_id)->toBe($adminUser->id);
    expect($entry->organization_id)->toBe($organization->id);
});
