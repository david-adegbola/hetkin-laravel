<?php

use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;

test('the schedule page shows nothing yet when no schedule exists', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('schedule'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/Schedule')
        ->where('schedule', null));
});

test('the schedule page shows the latest version\'s blocks', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 1]);
    $latest = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 2]);
    ScheduleBlock::factory()
        ->for($latest, 'schedule')
        ->for($site)
        ->create(['asset_label' => 'Line A']);

    $response = $this->actingAs($user, 'web')->get(route('schedule'));

    $response->assertInertia(fn ($page) => $page
        ->where('schedule.version', 2)
        ->has('schedule.blocks', 1)
        ->where('schedule.blocks.0.asset_label', 'Line A'));
});

test('submitting a valid block redirects back with a success status', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        'start_at' => '2024-01-01T08:00',
        'end_at' => '2024-01-01T12:00',
        'estimated_kwh' => '150',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'schedule-block-added');
});

test('the end time must be after the start time', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        'start_at' => '2024-01-01T12:00',
        'end_at' => '2024-01-01T08:00',
    ]);

    $response->assertSessionHasErrors('end_at');
});

test('estimated kWh must be positive when given', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        'start_at' => '2024-01-01T08:00',
        'end_at' => '2024-01-01T12:00',
        'estimated_kwh' => '0',
    ]);

    $response->assertSessionHasErrors('estimated_kwh');
});

test('the schedule history page lists every version', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 1]);
    ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 2]);

    $response = $this->actingAs($user, 'web')->get(route('schedule.history'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/ScheduleHistory')
        ->has('versions', 2));
});

test('a non-member cannot reach the schedule page', function () {
    $response = $this->get(route('schedule'));

    $response->assertRedirect(route('login'));
});
