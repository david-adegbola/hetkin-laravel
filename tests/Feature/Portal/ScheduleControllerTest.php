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
