<?php

use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('a member can view their organization\'s production schedule', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    Member::factory()->for($user)->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();

    expect(Gate::forUser($user)->allows('view', $schedule))->toBeTrue();
});

test('a non-member cannot view another organization\'s production schedule', function () {
    $user = User::factory()->create();
    $schedule = ProductionSchedule::factory()->create();

    expect(Gate::forUser($user)->allows('view', $schedule))->toBeFalse();
});
