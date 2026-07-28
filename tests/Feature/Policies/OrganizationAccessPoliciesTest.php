<?php

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * OrganizationPolicy/MemberPolicy/InvitationPolicy all share one shape:
 * visible to any member of the organization, gated on membership alone —
 * see each policy's docblock for why role isn't part of the check yet.
 * Feature scope (not Unit) since Member::isMemberOf() needs real rows in
 * the database, not just in-memory objects.
 */
test('a member can view their own organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();

    expect(Gate::forUser($user)->allows('view', $organization))->toBeTrue();
});

test('a non-member cannot view an organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect(Gate::forUser($user)->allows('view', $organization))->toBeFalse();
});

test('a member of the organization can view a teammate\'s membership row', function () {
    $organization = Organization::factory()->create();
    $viewer = User::factory()->create();
    $teammate = User::factory()->create();
    Member::factory()->for($viewer)->for($organization, 'organization')->create();
    $teammateMembership = Member::factory()->for($teammate)->for($organization, 'organization')->create();

    expect(Gate::forUser($viewer)->allows('view', $teammateMembership))->toBeTrue();
});

test('a member of a different organization cannot view a membership row', function () {
    $outsider = User::factory()->create();
    Member::factory()->for($outsider)->for(Organization::factory(), 'organization')->create();
    $someoneElsesMembership = Member::factory()->create();

    expect(Gate::forUser($outsider)->allows('view', $someoneElsesMembership))->toBeFalse();
});

test('a member of the organization can view its pending invitations', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();
    $invitation = Invitation::factory()->for($organization, 'organization')->create();

    expect(Gate::forUser($user)->allows('view', $invitation))->toBeTrue();
});

test('a non-member cannot view another organization\'s invitation', function () {
    $user = User::factory()->create();
    $invitation = Invitation::factory()->create();

    expect(Gate::forUser($user)->allows('view', $invitation))->toBeFalse();
});
