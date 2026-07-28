<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Metering\Actions\UploadMeterReadingsAction;
use App\Domain\Metering\Enums\MeterDataSource;
use App\Domain\Metering\Exceptions\InvalidMeterCsvException;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;

function meterCsv(int $hours = 24, string $startHour = '2024-01-01T00:00', float $kwh = 10.0): string
{
    $lines = ['Start time,Quantity'];
    $ts = new DateTimeImmutable($startHour);
    for ($i = 0; $i < $hours; $i++) {
        $lines[] = $ts->modify("+{$i} hours")->format('Y-m-d\TH:i').','.$kwh;
    }

    return implode("\n", $lines);
}

test('creates a default site and metering point when none exist', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $result = app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, meterCsv(), 'export.csv');

    // Post-Action assertions querying Site directly need their own bound
    // CurrentOrganization, same as any other caller — the Action restores
    // whatever was bound before it ran (nothing, in this test) once it
    // returns; see UploadMeterReadingsAction::execute()'s docblock.
    app(CurrentOrganization::class)->set($organization);

    expect(Site::query()->where('organization_id', $organization->id)->count())->toBe(1);
    $site = Site::query()->where('organization_id', $organization->id)->first();
    expect($site->name)->toBe('Main site');

    expect($result->meteringPoint->label)->toBe('Manual upload');
    expect($result->meteringPoint->data_source)->toBe(MeterDataSource::ManualUpload);
    expect($result->meteringPoint->site_id)->toBe($site->id);
});

test('reuses an existing site and metering point instead of creating duplicates', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create(['name' => 'Existing site']);
    $meteringPoint = MeteringPoint::factory()->for($site)->create(['label' => 'Manual upload']);

    app(UploadMeterReadingsAction::class)->execute($organization, $user, meterCsv(), 'export.csv');

    app(CurrentOrganization::class)->set($organization);
    expect(Site::query()->where('organization_id', $organization->id)->count())->toBe(1);
    expect(MeteringPoint::query()->where('site_id', $site->id)->count())->toBe(1);
    expect(MeterReading::query()->where('metering_point_id', $meteringPoint->id)->count())->toBe(24);
});

test('writes every parsed row as a meter reading', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $result = app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, meterCsv(hours: 48), 'export.csv');

    expect($result->rowsWritten)->toBe(48);
    expect(MeterReading::query()->where('metering_point_id', $result->meteringPoint->id)->count())->toBe(48);
});

test('re-uploading an overlapping hour updates consumption instead of duplicating', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $action = app(UploadMeterReadingsAction::class);

    $first = $action->execute($organization, $user, meterCsv(kwh: 10.0), 'first.csv');
    $action->execute($organization, $user, meterCsv(kwh: 25.0), 'second.csv');

    expect(MeterReading::query()->where('metering_point_id', $first->meteringPoint->id)->count())->toBe(24);
    $reading = MeterReading::query()
        ->where('metering_point_id', $first->meteringPoint->id)
        ->orderBy('hour_start')
        ->first();
    expect((float) $reading->consumption_kwh)->toBe(25.0);
});

test('updates last_reading_at to the latest row and marks the connection healthy', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $meteringPoint = MeteringPoint::factory()->for($site)->create([
        'connection_healthy' => false,
    ]);

    $result = app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, meterCsv(hours: 24, startHour: '2024-03-01T00:00'), 'export.csv');

    expect($result->meteringPoint->id)->toBe($meteringPoint->id);
    expect($result->meteringPoint->connection_healthy)->toBeTrue();
    expect($result->meteringPoint->last_reading_at->toDateTimeString())
        ->toBe('2024-03-01 23:00:00');
});

test('writes an audit log entry scoped to the organization', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $result = app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, meterCsv(hours: 24), 'my-export.csv');

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('meter_data.uploaded');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->entity_type)->toBe('MeteringPoint');
    expect($entry->entity_id)->toBe($result->meteringPoint->id);
    expect($entry->metadata)->toBe(['rowCount' => 24, 'filename' => 'my-export.csv']);
});

test('a malformed CSV throws before touching the database', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, 'not,a,valid\nfile', 'bad.csv'))
        ->toThrow(InvalidMeterCsvException::class);

    // Parsing throws before the Action ever binds CurrentOrganization
    // (see its docblock: parsing happens before that), so this needs its
    // own binding, same as the other tests in this file.
    app(CurrentOrganization::class)->set($organization);
    expect(Site::query()->where('organization_id', $organization->id)->count())->toBe(0);
});
