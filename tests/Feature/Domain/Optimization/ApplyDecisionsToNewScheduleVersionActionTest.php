<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Actions\ApplyDecisionsToNewScheduleVersionAction;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;

test('throws when there is no schedule to apply changes to', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No schedule to apply changes to yet.');
});

test('throws when there are no accepted or partial decisions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();

    expect(fn () => app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No accepted or partially-adopted recommendations to apply yet.');
});

test('an accepted decision creates a new version with the proposed time applied', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 1]);
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'asset_label' => 'Line A',
        'start_at' => Carbon::parse('2026-01-01 12:00'),
        'end_at' => Carbon::parse('2026-01-01 13:00'),
    ]);
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create([
        'proposed_start_at' => Carbon::parse('2026-01-01 03:00'),
        'proposed_end_at' => Carbon::parse('2026-01-01 04:00'),
    ]);
    RecommendationDecision::factory()->for($recommendation)->create();

    $result = app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user);

    expect($result->newVersion)->toBe(2);
    expect($result->blocksChanged)->toBe(1);
    expect($result->schedule->version)->toBe(2);
    expect($result->schedule->status)->toBe(ScheduleStatus::Active);

    app(CurrentOrganization::class)->set($organization);
    $newBlock = ScheduleBlock::query()->where('schedule_id', $result->schedule->id)->first();
    expect($newBlock->asset_label)->toBe('Line A');
    expect($newBlock->start_at->toDateTimeString())->toBe('2026-01-01 03:00:00');
    expect($newBlock->end_at->toDateTimeString())->toBe('2026-01-01 04:00:00');

    $schedule->refresh();
    expect($schedule->status)->toBe(ScheduleStatus::Superseded);
});

test('a partial decision applies the adjusted time, not the proposed time', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create([
        'proposed_start_at' => Carbon::parse('2026-01-01 03:00'),
        'proposed_end_at' => Carbon::parse('2026-01-01 04:00'),
    ]);
    RecommendationDecision::factory()->for($recommendation)->partial()->create([
        'adjusted_start_at' => Carbon::parse('2026-01-01 05:00'),
        'adjusted_end_at' => Carbon::parse('2026-01-01 06:00'),
    ]);

    $result = app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user);

    app(CurrentOrganization::class)->set($organization);
    $newBlock = ScheduleBlock::query()->where('schedule_id', $result->schedule->id)->first();
    expect($newBlock->start_at->toDateTimeString())->toBe('2026-01-01 05:00:00');
    expect($newBlock->end_at->toDateTimeString())->toBe('2026-01-01 06:00:00');
});

test('a rejected decision is not rolled forward', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->create();
    RecommendationDecision::factory()->for($recommendation)->rejected()->create();

    expect(fn () => app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user))
        ->toThrow(OptimizationActionException::class, 'No accepted or partially-adopted recommendations to apply yet.');
});

test('unaffected blocks are carried forward unchanged', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    $decidedBlock = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $untouchedBlock = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'asset_label' => 'Untouched Line',
        'start_at' => Carbon::parse('2026-02-01 08:00'),
        'end_at' => Carbon::parse('2026-02-01 09:00'),
    ]);
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($decidedBlock, 'scheduleBlock')->create();
    RecommendationDecision::factory()->for($recommendation)->create();

    $result = app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user);

    app(CurrentOrganization::class)->set($organization);
    $carried = ScheduleBlock::query()
        ->where('schedule_id', $result->schedule->id)
        ->where('asset_label', 'Untouched Line')
        ->first();
    expect($carried)->not->toBeNull();
    expect($carried->start_at->toDateTimeString())->toBe('2026-02-01 08:00:00');
});

test('writes an audit log entry with version and block-change counts', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 4]);
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create();
    RecommendationDecision::factory()->for($recommendation)->create();

    $result = app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('schedule.new_version_applied');
    expect($entry->metadata['previousVersion'])->toBe(4);
    expect($entry->metadata['newVersion'])->toBe(5);
    expect($entry->metadata['blocksChanged'])->toBe(1);
    expect($result->schedule->version)->toBe(5);
});
