<?php

use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Services\OrganizationContextResolver;
use App\Models\User;

/**
 * Ports requirePortalContext()'s resolution logic (src/lib/portal-context.ts)
 * — see OrganizationContextResolver's docblock for the exact behavior being
 * matched.
 */
test('resolves the session\'s active organization when it exists', function () {
    $user = User::factory()->create();
    $activeOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    Member::factory()->for($user)->for($activeOrg, 'organization')->create();
    Member::factory()->for($user)->for($otherOrg, 'organization')->create();

    $resolved = (new OrganizationContextResolver)->resolve($user, $activeOrg->id);

    expect($resolved->id)->toBe($activeOrg->id);
});

test('falls back to the first membership when there is no session active organization', function () {
    $user = User::factory()->create();
    $firstOrg = Organization::factory()->create();
    $secondOrg = Organization::factory()->create();
    Member::factory()->for($user)->for($firstOrg, 'organization')->create(['created_at' => now()->subDay()]);
    Member::factory()->for($user)->for($secondOrg, 'organization')->create(['created_at' => now()]);

    $resolved = (new OrganizationContextResolver)->resolve($user, null);

    expect($resolved->id)->toBe($firstOrg->id);
});

test('falls back to the first membership when the session active organization no longer exists', function () {
    $user = User::factory()->create();
    $membershipOrg = Organization::factory()->create();
    Member::factory()->for($user)->for($membershipOrg, 'organization')->create();

    $resolved = (new OrganizationContextResolver)->resolve($user, 'org_does_not_exist');

    expect($resolved->id)->toBe($membershipOrg->id);
});

test('resolves null for a user with no organization membership at all', function () {
    $user = User::factory()->create();

    $resolved = (new OrganizationContextResolver)->resolve($user, null);

    expect($resolved)->toBeNull();
});
