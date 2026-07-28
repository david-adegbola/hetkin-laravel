<?php

use App\Domain\Alerts\Actions\GenerateAlertsAction;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;

test('skips organizations that are not PILOT or ACTIVE', function () {
    Organization::factory()->create(['status' => OrganizationStatus::Churned]);
    Organization::factory()->create(['status' => OrganizationStatus::Paused]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result)->toBe(['organizationsChecked' => 0, 'alertsCreated' => 0, 'results' => []]);
});

test('an org with no metering points at all is skipped, not flagged as a gap', function () {
    $org = Organization::factory()->create(['status' => OrganizationStatus::Pilot]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result['organizationsChecked'])->toBe(1);
    expect($result['alertsCreated'])->toBe(0);
    expect($result['results'][0])->toBe(['organizationId' => $org->id, 'alertCreated' => false]);
});

test('flags a stale org (no reading in over 7 days) with a DATA_GAP alert', function () {
    $org = Organization::factory()->create(['status' => OrganizationStatus::Active]);
    $site = Site::factory()->create(['organization_id' => $org->id]);
    MeteringPoint::factory()->create([
        'site_id' => $site->id,
        'last_reading_at' => now()->subDays(10),
        'connection_healthy' => true,
    ]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result['alertsCreated'])->toBe(1);
    expect($result['results'][0])->toBe(['organizationId' => $org->id, 'alertCreated' => true]);
    $alert = Alert::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $org->id)->first();
    expect($alert->type)->toBe(AlertType::DataGap);
    expect($alert->title)->toBe("Your meter data hasn't updated recently");
});

test('flags an org with an unhealthy metering point even with a recent reading', function () {
    $org = Organization::factory()->create(['status' => OrganizationStatus::Active]);
    $site = Site::factory()->create(['organization_id' => $org->id]);
    MeteringPoint::factory()->create([
        'site_id' => $site->id,
        'last_reading_at' => now()->subDay(),
        'connection_healthy' => false,
    ]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result['alertsCreated'])->toBe(1);
    $alert = Alert::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $org->id)->first();
    expect($alert->title)->toBe('A metering point needs attention');
});

test('a healthy org with a recent reading is not flagged', function () {
    $org = Organization::factory()->create(['status' => OrganizationStatus::Active]);
    $site = Site::factory()->create(['organization_id' => $org->id]);
    MeteringPoint::factory()->create([
        'site_id' => $site->id,
        'last_reading_at' => now()->subDay(),
        'connection_healthy' => true,
    ]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result['alertsCreated'])->toBe(0);
    expect($result['results'][0]['alertCreated'])->toBeFalse();
});

test('counts as created even when CreateAlertAction dedupes internally, matching v2 exactly', function () {
    $org = Organization::factory()->create(['status' => OrganizationStatus::Active]);
    $site = Site::factory()->create(['organization_id' => $org->id]);
    MeteringPoint::factory()->create([
        'site_id' => $site->id,
        'last_reading_at' => now()->subDays(10),
        'connection_healthy' => true,
    ]);
    Alert::withoutGlobalScope(OrganizationScope::class)->create([
        'organization_id' => $org->id,
        'type' => AlertType::DataGap,
        'severity' => AlertSeverity::Warning,
        'title' => 'existing',
        'body' => 'existing',
    ]);

    $result = app(GenerateAlertsAction::class)->execute();

    expect($result['alertsCreated'])->toBe(1);
    expect(Alert::withoutGlobalScope(OrganizationScope::class)->where('organization_id', $org->id)->count())->toBe(1);
});
