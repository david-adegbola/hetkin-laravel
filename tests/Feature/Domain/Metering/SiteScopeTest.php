<?php

use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;

/**
 * Site has a direct organization_id (BelongsToOrganization); MeteringPoint
 * and MeterReading don't — see those models' docblocks and
 * MIGRATION_PLAN.md ADR-29.
 */
test('Site is scoped like any other BelongsToOrganization model', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Site::factory()->for($orgA, 'organization')->create();
    Site::factory()->for($orgB, 'organization')->create();

    app(CurrentOrganization::class)->set($orgA);

    expect(Site::query()->get())->toHaveCount(1);
});

test('querying Site with no CurrentOrganization bound throws', function () {
    Site::factory()->create();

    expect(fn () => Site::query()->get())->toThrow(LogicException::class);
});

test('MeteringPoint is reachable regardless of CurrentOrganization binding, scoped only via its site', function () {
    $orgA = Organization::factory()->create();
    $site = Site::factory()->for($orgA, 'organization')->create();
    MeteringPoint::factory()->for($site)->create();

    // No CurrentOrganization bound at all — MeteringPoint has no
    // BelongsToOrganization trait, so this must not throw.
    expect(MeteringPoint::query()->count())->toBe(1);
});

test('a metering point\'s organization is reachable through its site relation', function () {
    $organization = Organization::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $meteringPoint = MeteringPoint::factory()->for($site)->create();

    // Lazy-loading ->site queries Site, which is scoped — same as any
    // other realistic caller, this needs a bound CurrentOrganization.
    app(CurrentOrganization::class)->set($organization);

    expect($meteringPoint->site->organization_id)->toBe($organization->id);
});
