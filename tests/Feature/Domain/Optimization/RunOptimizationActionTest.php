<?php

use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Actions\RunOptimizationAction;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;

function seedDayPrices(Carbon $day, array $eurPerMwhByHour): void
{
    foreach ($eurPerMwhByHour as $hour => $price) {
        ElectricityPrice::factory()->create([
            'bidding_zone' => BiddingZone::FI,
            'hour_start' => $day->copy()->utc()->startOfDay()->addHours($hour),
            'eur_per_mwh' => $price,
            'source' => PriceSource::Entsoe,
        ]);
    }
}

test('throws when the organization has no schedule blocks', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(RunOptimizationAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'Add at least one schedule block first.');
});

test('throws when no block has an estimated energy use', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->withoutEstimatedKwh()->create();

    expect(fn () => app(RunOptimizationAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No schedule blocks are eligible for optimization yet');
});

test('throws when the only blocks with an estimated energy use are labour-bound', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->labourBound()->create([
        'estimated_kwh' => 500,
    ]);

    expect(fn () => app(RunOptimizationAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No schedule blocks are eligible for optimization yet');
});

test('a labour-bound block never produces a recommendation, even in a materially cheaper window', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    // One eligible Unmanned block already in its cheapest window (so it
    // alone would produce zero recommendations) alongside a LabourBound
    // block sitting in the most expensive hour — if the labour filter
    // were leaking, this block would be the one generating a
    // recommendation.
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => Carbon::parse('2026-01-01 03:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 04:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->labourBound()->create([
        'start_at' => Carbon::parse('2026-01-01 12:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 13:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    $prices = array_fill(0, 24, 100.0);
    $prices[3] = 10.0;
    seedDayPrices(Carbon::parse('2026-01-01', 'UTC'), $prices);

    $run = app(RunOptimizationAction::class)->execute($organization, $user);

    app(CurrentOrganization::class)->set($organization);
    expect(Recommendation::query()->where('optimization_run_id', $run->id)->count())->toBe(0);
});

test('throws when no price data covers the schedule\'s dates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => Carbon::parse('2026-01-01 12:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 13:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);

    expect(fn () => app(RunOptimizationAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No price data covers your schedule\'s dates yet.');
});

test('creates an optimization run and recommendations for a materially cheaper window', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'asset_label' => 'Line A',
        'start_at' => Carbon::parse('2026-01-01 12:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 13:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    $prices = array_fill(0, 24, 100.0);
    $prices[3] = 10.0;
    seedDayPrices(Carbon::parse('2026-01-01', 'UTC'), $prices);

    $run = app(RunOptimizationAction::class)->execute($organization, $user);

    expect($run)->toBeInstanceOf(OptimizationRun::class);
    expect($run->methodology_version)->toBe('provisional-window-shift-v1');

    app(CurrentOrganization::class)->set($organization);
    expect(OptimizationRun::query()->count())->toBe(1);
    expect(Recommendation::query()->where('optimization_run_id', $run->id)->count())->toBe(1);

    $alert = Alert::query()->where('organization_id', $organization->id)->first();
    expect($alert)->not->toBeNull();
    expect($alert->type)->toBe(AlertType::NewOpportunity);
    expect($alert->title)->toBe('1 new recommendation ready to review');
});

test('writes an audit log entry with the recommendation count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => Carbon::parse('2026-01-01 12:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 13:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    $prices = array_fill(0, 24, 100.0);
    $prices[3] = 10.0;
    seedDayPrices(Carbon::parse('2026-01-01', 'UTC'), $prices);

    $run = app(RunOptimizationAction::class)->execute($organization, $user);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('optimization_run.completed');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->entity_id)->toBe($run->id);
    expect($entry->metadata['recommendationCount'])->toBe(1);
});

test('produces no recommendations when the schedule is already in the cheapest window', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => Carbon::parse('2026-01-01 03:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-01 04:00', 'UTC'),
        'estimated_kwh' => 500,
    ]);
    $prices = array_fill(0, 24, 100.0);
    $prices[3] = 10.0;
    seedDayPrices(Carbon::parse('2026-01-01', 'UTC'), $prices);

    $run = app(RunOptimizationAction::class)->execute($organization, $user);

    app(CurrentOrganization::class)->set($organization);
    expect(Recommendation::query()->where('optimization_run_id', $run->id)->count())->toBe(0);
    expect(Alert::query()->where('organization_id', $organization->id)->count())->toBe(0);
});
