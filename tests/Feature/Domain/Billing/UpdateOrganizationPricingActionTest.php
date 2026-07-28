<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\UpdateOrganizationPricingAction;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Organizations\Models\Organization;
use App\Models\AdminUser;

test('sets base fee and revenue share from raw form strings', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    $updated = app(UpdateOrganizationPricingAction::class)->execute($organization, $admin, '500.50', '12.5');

    expect($updated->base_fee_cents)->toBe(50_050);
    expect((float) $updated->revenue_share_percent)->toBe(12.5);
});

test('an empty string clears the field to null', function () {
    $organization = Organization::factory()->create(['base_fee_cents' => 10_000, 'revenue_share_percent' => 5]);
    $admin = AdminUser::factory()->create();

    $updated = app(UpdateOrganizationPricingAction::class)->execute($organization, $admin, '', '');

    expect($updated->base_fee_cents)->toBeNull();
    expect($updated->revenue_share_percent)->toBeNull();
});

test('rejects a negative base fee', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(UpdateOrganizationPricingAction::class)->execute($organization, $admin, '-10', null))
        ->toThrow(BillingActionException::class, 'Base fee must be a non-negative number.');
});

test('rejects a revenue share outside 0-100', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(UpdateOrganizationPricingAction::class)->execute($organization, $admin, null, '150'))
        ->toThrow(BillingActionException::class, 'Revenue share must be between 0 and 100.');
});

test('writes an audit log entry with before/after values', function () {
    $organization = Organization::factory()->create(['base_fee_cents' => 10_000]);
    $admin = AdminUser::factory()->create();

    app(UpdateOrganizationPricingAction::class)->execute($organization, $admin, '200', null);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('organization.pricing_changed');
    expect($entry->metadata['from']['baseFeeCents'])->toBe(10_000);
    expect($entry->metadata['to']['baseFeeCents'])->toBe(20_000);
});
