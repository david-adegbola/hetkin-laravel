<?php

use App\Domain\Alerts\Actions\CreateAlertAction;
use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;

test('creates an alert with the given fields', function () {
    $organization = Organization::factory()->create();

    $alert = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::DataGap,
        AlertSeverity::Warning,
        'Title',
        'Body',
    );

    expect($alert)->not->toBeNull();
    expect($alert->organization_id)->toBe($organization->id);
    expect($alert->type)->toBe(AlertType::DataGap);
    expect($alert->severity)->toBe(AlertSeverity::Warning);
    expect($alert->read_at)->toBeNull();
});

test('is a silent no-op when an unread alert of the same type exists within the last 20 hours', function () {
    $organization = Organization::factory()->create();
    Alert::factory()->for($organization, 'organization')->create([
        'type' => AlertType::DataGap,
        'created_at' => now()->subHours(5),
    ]);

    $result = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::DataGap,
        AlertSeverity::Warning,
        'New title',
        'New body',
    );

    expect($result)->toBeNull();
    expect(Alert::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->count())->toBe(1);
});

test('a different alert type is not deduped against an existing one', function () {
    $organization = Organization::factory()->create();
    Alert::factory()->for($organization, 'organization')->create(['type' => AlertType::DataGap]);

    $result = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::VarianceFlag,
        AlertSeverity::Warning,
        'Title',
        'Body',
    );

    expect($result)->not->toBeNull();
    expect(Alert::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->count())->toBe(2);
});

test('a read alert does not suppress a new one, even within the window', function () {
    $organization = Organization::factory()->create();
    Alert::factory()->read()->for($organization, 'organization')->create([
        'type' => AlertType::DataGap,
        'created_at' => now()->subHours(2),
    ]);

    $result = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::DataGap,
        AlertSeverity::Warning,
        'Title',
        'Body',
    );

    expect($result)->not->toBeNull();
});

test('an alert older than the 20 hour window does not suppress a new one', function () {
    $organization = Organization::factory()->create();
    Alert::factory()->for($organization, 'organization')->create([
        'type' => AlertType::DataGap,
        'created_at' => now()->subHours(21),
    ]);

    $result = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::DataGap,
        AlertSeverity::Warning,
        'Title',
        'Body',
    );

    expect($result)->not->toBeNull();
});

test('does not require an active transaction, unlike AuditLogService', function () {
    $organization = Organization::factory()->create();

    // Deliberately NOT wrapped in DB::transaction() — this is the point of
    // the test: unlike App\Domain\Audit\Services\AuditLogService::record(),
    // this Action must work standalone, matching every real call site
    // (always invoked after the domain transaction has already committed).
    $alert = app(CreateAlertAction::class)->execute(
        $organization->id,
        AlertType::ReportReady,
        AlertSeverity::Info,
        'Title',
        'Body',
    );

    expect($alert)->not->toBeNull();
});
