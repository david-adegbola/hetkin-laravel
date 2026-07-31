<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Actions\ImportScheduleBlocksAction;
use App\Domain\Scheduling\Enums\LabourClassification;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Exceptions\InvalidScheduleCsvException;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;

function scheduleCsv(string $rows): string
{
    return "Asset,Planned start,Planned end,Estimated kWh,Labour classification,Site,Description\n{$rows}";
}

test('imports an ERP CSV into a new immutable schedule version', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $previous = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 2, 'status' => ScheduleStatus::Active]);

    $result = app(ImportScheduleBlocksAction::class)->execute(
        $organization,
        $user,
        scheduleCsv("Extruder 2,2026-08-01 08:00,2026-08-01 12:00,150.5,UNMANNED,Plant A,Weekend run\nKiln 1,2026-08-02 09:00,2026-08-02 11:00,,LABOUR_BOUND,Plant B,"),
        'erp-export.csv',
    );

    expect($result->rowsWritten)->toBe(2);
    expect($result->schedule->version)->toBe(3);
    expect($result->schedule->status)->toBe(ScheduleStatus::Draft);
    expect($previous->refresh()->status)->toBe(ScheduleStatus::Superseded);
    expect(app(CurrentOrganization::class)->get())->toBeNull();

    app(CurrentOrganization::class)->set($organization);
    $blocks = ScheduleBlock::query()->where('schedule_id', $result->schedule->id)->with('site')->orderBy('asset_label')->get();
    expect($blocks)->toHaveCount(2);
    expect($blocks[0]->asset_label)->toBe('Extruder 2');
    expect((float) $blocks[0]->estimated_kwh)->toBe(150.5);
    expect($blocks[0]->labour_classification)->toBe(LabourClassification::Unmanned);
    expect($blocks[0]->site->name)->toBe('Plant A');
    expect($blocks[0]->start_at->utc()->format('Y-m-d H:i'))->toBe('2026-08-01 05:00');
    expect($blocks[1]->labour_classification)->toBe(LabourClassification::LabourBound);
    expect($blocks[1]->estimated_kwh)->toBeNull();

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('schedule.imported');
    expect($entry->metadata)->toMatchArray(['filename' => 'erp-export.csv', 'rowCount' => 2, 'newVersion' => 3]);
});

test('rejects an invalid import before creating a schedule', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(ImportScheduleBlocksAction::class)->execute(
        $organization,
        $user,
        scheduleCsv('Line A,2026-08-01 12:00,2026-08-01 08:00,20,UNMANNED,Main site,'),
        'invalid.csv',
    ))->toThrow(InvalidScheduleCsvException::class, 'must end after it starts');

    app(CurrentOrganization::class)->set($organization);
    expect(ProductionSchedule::query()->count())->toBe(0);
});

test('rejects fields that cannot fit in the schedule schema', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(ImportScheduleBlocksAction::class)->execute(
        $organization,
        $user,
        scheduleCsv(str_repeat('A', 256).',2026-08-01 08:00,2026-08-01 12:00,20,UNMANNED,Main site,'),
        'invalid.csv',
    ))->toThrow(InvalidScheduleCsvException::class, 'asset label longer than 255 characters');
});
