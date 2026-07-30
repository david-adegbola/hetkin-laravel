<?php

use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Enums\LabourClassification;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Support\Carbon;

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
        'labour_classification' => 'UNMANNED',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'schedule-block-added');
    expect(ScheduleBlock::query()->latest('created_at')->first()->labour_classification)
        ->toBe(LabourClassification::Unmanned);
});

/*
 * Regression coverage for a real production incident: a pilot user typed
 * "7am–3pm" (their own local wall-clock time, Finland) into the
 * <input type="datetime-local"> and the app displayed it back as
 * 10:00–18:00. Root cause was ScheduleController::store() calling
 * Carbon::parse() with no timezone argument on that timezone-naive
 * string, which interprets it in config('app.timezone') (UTC) — silently
 * treating "7am Finland" as "7am UTC" instead of converting it. The fix
 * parses in config('scheduling.timezone') (Europe/Helsinki) first, then
 * converts to UTC for storage.
 */
test('a submitted start/end time is interpreted as Helsinki local time, not UTC', function () {
    ['user' => $user] = portalUser();

    $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        // Winter (EET, UTC+2) — deliberately not summer (EEST, UTC+3),
        // so this test can't pass by coincidence if some other UTC+3
        // constant were hardcoded instead of a real timezone conversion.
        'start_at' => '2024-01-15T07:00',
        'end_at' => '2024-01-15T15:00',
        'labour_classification' => 'UNMANNED',
    ]);

    $block = ScheduleBlock::query()->latest('created_at')->first();

    expect($block->start_at->equalTo(Carbon::parse('2024-01-15T07:00', 'Europe/Helsinki')))->toBeTrue();
    expect($block->start_at->utc()->format('H:i'))->toBe('05:00');
    expect($block->end_at->utc()->format('H:i'))->toBe('13:00');
});

test('labour classification must be a recognized value', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        'start_at' => '2024-01-01T08:00',
        'end_at' => '2024-01-01T12:00',
        'labour_classification' => 'SOMETHING_ELSE',
    ]);

    $response->assertSessionHasErrors('labour_classification');
});

test('labour classification is required', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.store'), [
        'asset_label' => 'Extruder 2',
        'start_at' => '2024-01-01T08:00',
        'end_at' => '2024-01-01T12:00',
    ]);

    $response->assertSessionHasErrors('labour_classification');
});

test('the schedule page reports eligibility using labour classification, not just estimated kWh', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->labourBound()->create([
        'estimated_kwh' => 500,
    ]);

    $response = $this->actingAs($user, 'web')->get(route('schedule'));

    $response->assertInertia(fn ($page) => $page
        ->where('schedule.hasEligibleBlocks', false)
        ->where('schedule.blocks.0.labour_classification', 'LABOUR_BOUND'));
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

test('running optimization redirects back with a recommendation count', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => Carbon::parse('2026-01-01 12:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 13:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    $prices = array_fill(0, 24, 100.0);
    $prices[3] = 10.0;
    foreach ($prices as $hour => $price) {
        ElectricityPrice::factory()->create([
            'bidding_zone' => BiddingZone::FI,
            'hour_start' => Carbon::parse('2026-01-01', 'UTC')->startOfDay()->addHours($hour),
            'eur_per_mwh' => $price,
            'source' => PriceSource::Entsoe,
        ]);
    }

    $response = $this->actingAs($user, 'web')->post(route('schedule.optimize'));

    $response->assertRedirect();
    $response->assertSessionHas('optimizationStatus', fn ($status) => $status['recommendationCount'] === 1);
});

test('running optimization with no schedule blocks redirects back with an error', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('schedule.optimize'));

    $response->assertSessionHasErrors('optimization');
});

test('applying decisions redirects back with the new version', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 1]);
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create();
    RecommendationDecision::factory()->for($recommendation)->create(['decision' => RecommendationDecisionType::Accepted]);

    $response = $this->actingAs($user, 'web')->post(route('schedule.apply-decisions'));

    $response->assertRedirect();
    $response->assertSessionHas('applyDecisionsStatus', fn ($status) => $status['newVersion'] === 2);
    expect(ProductionSchedule::query()->where('organization_id', $organization->id)->count())->toBe(2);
});

test('applying decisions with nothing accepted redirects back with an error', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();

    $response = $this->actingAs($user, 'web')->post(route('schedule.apply-decisions'));

    $response->assertSessionHasErrors('applyDecisions');
});
