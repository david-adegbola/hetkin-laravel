<?php

use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('a member can view their organization\'s site', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();
    $site = Site::factory()->for($organization, 'organization')->create();

    expect(Gate::forUser($user)->allows('view', $site))->toBeTrue();
});

test('a non-member cannot view a site belonging to another organization', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    expect(Gate::forUser($user)->allows('view', $site))->toBeFalse();
});

test('a member can view a metering point via its site\'s organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $meteringPoint = MeteringPoint::factory()->for($site)->create();

    expect(Gate::forUser($user)->allows('view', $meteringPoint))->toBeTrue();
});

test('a non-member cannot view another organization\'s metering point', function () {
    $user = User::factory()->create();
    $meteringPoint = MeteringPoint::factory()->create();

    expect(Gate::forUser($user)->allows('view', $meteringPoint))->toBeFalse();
});
