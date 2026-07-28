<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;

/**
 * The Eloquent-layer tenant boundary (MIGRATION_PLAN.md ADR-25/ADR-26) —
 * fails closed by design, unlike v2's own current posture (its RLS
 * policies exist but aren't FORCEd yet, so an accidental raw `db` query
 * there silently sees every organization's rows).
 */
test('querying a BelongsToOrganization model with no CurrentOrganization bound throws', function () {
    Member::factory()->create();

    expect(fn () => Member::query()->get())->toThrow(LogicException::class);
});

test('binding a CurrentOrganization scopes queries to that organization only', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Member::factory()->for($orgA, 'organization')->create();
    Member::factory()->for($orgB, 'organization')->create();

    app(CurrentOrganization::class)->set($orgA);

    $members = Member::query()->get();

    expect($members)->toHaveCount(1);
    expect($members->first()->organization_id)->toBe($orgA->id);
});

test('withoutGlobalScope bypasses the tenant boundary for a legitimate cross-tenant query', function () {
    Organization::factory()->count(2)->create()->each(
        fn (Organization $org) => Member::factory()->for($org, 'organization')->create(),
    );

    // No CurrentOrganization bound at all — would throw without the
    // explicit opt-out, per the first test above.
    $members = Member::withoutGlobalScope(OrganizationScope::class)->get();

    expect($members)->toHaveCount(2);
});

test('creating a scoped model without an explicit organization_id inherits the bound CurrentOrganization', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);

    $member = Member::create([
        'user_id' => User::factory()->create()->id,
        'role' => 'VIEWER',
    ]);

    expect($member->organization_id)->toBe($organization->id);
});

test('an explicit organization_id on create is not overridden by the bound CurrentOrganization', function () {
    $boundOrg = Organization::factory()->create();
    $explicitOrg = Organization::factory()->create();
    app(CurrentOrganization::class)->set($boundOrg);

    $member = Member::create([
        'organization_id' => $explicitOrg->id,
        'user_id' => User::factory()->create()->id,
        'role' => 'VIEWER',
    ]);

    expect($member->organization_id)->toBe($explicitOrg->id);
});

test('Invitation is scoped the same way as Member', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Invitation::factory()->for($orgA, 'organization')->create();
    Invitation::factory()->for($orgB, 'organization')->create();

    app(CurrentOrganization::class)->set($orgA);

    expect(Invitation::query()->get())->toHaveCount(1);
});

test('Organization itself is never scoped — it is the tenant, not a tenant-owned row', function () {
    Organization::factory()->count(3)->create();

    // No CurrentOrganization bound; Organization has no BelongsToOrganization
    // trait, so this must not throw and must see every row.
    expect(Organization::query()->count())->toBe(3);
});
