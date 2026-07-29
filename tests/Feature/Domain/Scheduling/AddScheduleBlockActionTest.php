<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Actions\AddScheduleBlockAction;
use App\Domain\Scheduling\Enums\LabourClassification;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;

test('creates a default site and a version-1 draft schedule when none exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $block = app(AddScheduleBlockAction::class)->execute(
        $organization,
        $user,
        'Extruder 2',
        Carbon::parse('2024-01-01 08:00'),
        Carbon::parse('2024-01-01 12:00'),
        150.0,
    );

    app(CurrentOrganization::class)->set($organization);
    $site = Site::query()->where('organization_id', $organization->id)->first();
    expect($site->name)->toBe('Main site');

    $schedule = ProductionSchedule::query()->where('organization_id', $organization->id)->first();
    expect($schedule->version)->toBe(1);
    expect($schedule->status)->toBe(ScheduleStatus::Draft);
    expect($schedule->submitted_by_id)->toBe($user->id);

    expect($block->schedule_id)->toBe($schedule->id);
    expect($block->site_id)->toBe($site->id);
    expect($block->asset_label)->toBe('Extruder 2');
    expect((float) $block->estimated_kwh)->toBe(150.0);
});

test('reuses the existing site and the latest schedule version', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $latestSchedule = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 3]);
    ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 2]);

    $block = app(AddScheduleBlockAction::class)->execute(
        $organization,
        $user,
        'Line A',
        Carbon::parse('2024-01-01 08:00'),
        Carbon::parse('2024-01-01 12:00'),
        null,
    );

    app(CurrentOrganization::class)->set($organization);
    expect(Site::query()->where('organization_id', $organization->id)->count())->toBe(1);
    expect(ProductionSchedule::query()->where('organization_id', $organization->id)->count())->toBe(2);
    expect($block->schedule_id)->toBe($latestSchedule->id);
    expect($block->site_id)->toBe($site->id);
    expect($block->estimated_kwh)->toBeNull();
});

test('writes an audit log entry scoped to the organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $block = app(AddScheduleBlockAction::class)->execute(
        $organization,
        $user,
        'Kiln 1',
        Carbon::parse('2024-01-01 08:00'),
        Carbon::parse('2024-01-01 12:00'),
        null,
    );

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('schedule_block.created');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->entity_type)->toBe('ScheduleBlock');
    expect($entry->entity_id)->toBe($block->id);
});

test('defaults to Labour Bound when no classification is given', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $block = app(AddScheduleBlockAction::class)->execute(
        $organization,
        $user,
        'Extruder 2',
        Carbon::parse('2024-01-01 08:00'),
        Carbon::parse('2024-01-01 12:00'),
        150.0,
    );

    expect($block->labour_classification)->toBe(LabourClassification::LabourBound);
});

test('stores an explicitly given classification', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $block = app(AddScheduleBlockAction::class)->execute(
        $organization,
        $user,
        'Compressor 1',
        Carbon::parse('2024-01-01 08:00'),
        Carbon::parse('2024-01-01 12:00'),
        150.0,
        LabourClassification::Unmanned,
    );

    expect($block->labour_classification)->toBe(LabourClassification::Unmanned);
});

test('each call adds a block without creating duplicate sites or schedules', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $action = app(AddScheduleBlockAction::class);

    $action->execute($organization, $user, 'Line A', Carbon::parse('2024-01-01 08:00'), Carbon::parse('2024-01-01 12:00'), null);
    $action->execute($organization, $user, 'Line B', Carbon::parse('2024-01-02 08:00'), Carbon::parse('2024-01-02 12:00'), null);

    app(CurrentOrganization::class)->set($organization);
    expect(Site::query()->where('organization_id', $organization->id)->count())->toBe(1);
    expect(ProductionSchedule::query()->where('organization_id', $organization->id)->count())->toBe(1);
    $schedule = ProductionSchedule::query()->where('organization_id', $organization->id)->first();
    expect(ScheduleBlock::query()->where('schedule_id', $schedule->id)->count())->toBe(2);
});
