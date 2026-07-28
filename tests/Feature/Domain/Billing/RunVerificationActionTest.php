<?php

use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Billing\Contracts\VerificationCalculationService;
use App\Domain\Billing\DTOs\VerificationCalculationResult;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use Illuminate\Support\Carbon;

function seedVerificationPeriodData(Organization $organization, Carbon $periodStart, float $consumptionKwh, float $priceEurPerMwh, int $days = 30): void
{
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();

    for ($d = 0; $d < $days; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = $periodStart->copy()->addDays($d)->addHours($h);
            MeterReading::factory()->for($point, 'meteringPoint')->create([
                'hour_start' => $hourStart,
                'consumption_kwh' => $consumptionKwh,
            ]);
            ElectricityPrice::factory()->create([
                'bidding_zone' => BiddingZone::FI,
                'hour_start' => $hourStart,
                'eur_per_mwh' => $priceEurPerMwh,
                'source' => PriceSource::Entsoe,
            ]);
        }
    }
}

test('skips organizations with no locked baseline', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->create(); // DRAFT, not locked

    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    $result = app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect($result['organizationsConsidered'])->toBe(0);
});

test('computes a verification record for an org with a locked baseline and enough data', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->locked()->for($organization, 'organization')->create([
        'baseline_annual_cost_cents' => 10_950_000,
    ]);
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    $result = app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect($result['organizationsConsidered'])->toBe(1);
    expect($result['results'][0]['status'])->toBe('computed');

    $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->first();
    expect($record)->not->toBeNull();
    expect($record->baseline_id)->toBe($baseline->id);
    expect($record->review_status)->toBe(VerificationReviewStatus::ShadowPendingReview);
});

test('computes a verification record with no CurrentOrganization bound, matching the real cron/admin-trigger path', function () {
    // Every other test in this file binds CurrentOrganization first, which
    // silently masks a genuinely cross-tenant bug: this Action's real
    // callers (a future scheduled command, and App\Domain\Jobs\Actions\
    // TriggerRunVerificationAction's admin "Run now") never bind one. A
    // live smoke test caught exactly this — a `whereHas('meteringPoint.
    // site', ...)` constraint on the scoped Site model with no
    // `withoutGlobalScope()` inside the closure, which throws
    // App\Domain\Organizations\Scopes\OrganizationScope's fail-closed
    // guard the moment no CurrentOrganization is bound.
    $organization = Organization::factory()->create();
    $baseline = Baseline::factory()->locked()->for($organization, 'organization')->create([
        'baseline_annual_cost_cents' => 10_950_000,
    ]);
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    $result = app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect($result['results'][0]['status'])->toBe('computed');
    $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->first();
    expect($record)->not->toBeNull();
    expect($record->baseline_id)->toBe($baseline->id);
});

test('skips an org with a locked baseline but insufficient period data', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    // Only 2 days of data - well under the 5-day (120 hour) minimum.
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 2);

    $result = app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect($result['results'][0]['status'])->toBe('skipped');
});

test('re-running the same period refreshes figures but never touches review_status', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create([
        'baseline_annual_cost_cents' => 10_950_000,
    ]);
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    app(RunVerificationAction::class)->execute($periodStart, $periodEnd);
    $first = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->first();
    $first->update(['review_status' => VerificationReviewStatus::LiveApproved]);

    app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect(VerificationRecord::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->count())->toBe(1);
    $refreshed = $first->refresh();
    expect($refreshed->review_status)->toBe(VerificationReviewStatus::LiveApproved);
});

test('defaults to the previous full UTC calendar month when no period is given', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();

    $result = app(RunVerificationAction::class)->execute();

    $expectedStart = now('UTC')->startOfMonth()->subMonthNoOverflow();
    expect(Carbon::parse($result['periodStart'])->isSameDay($expectedStart))->toBeTrue();
});

test('writes an audit log entry with SYSTEM actor', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('verification_record.computed');
    expect($entry->actor_type->value)->toBe('SYSTEM');
    expect($entry->actor_id)->toBe('run-verification-job');
});

test('creates a VARIANCE_FLAG alert when the calculation flags variance', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    // Mocked rather than crafted from raw meter/price data — the exact
    // variance threshold is App\Domain\Billing\Strategies\
    // ProvisionalPeriodComparisonStrategy's own concern (covered by its
    // own tests); this test only needs to prove RunVerificationAction
    // wires an alert off `varianceFlagged`, whatever produced it.
    $this->mock(VerificationCalculationService::class, function ($mock) {
        $mock->shouldReceive('calculate')->once()->andReturn(new VerificationCalculationResult(
            methodologyVersion: 'provisional-period-comparison-v1',
            isProvisional: true,
            baselineCostCents: 900_000,
            actualCostCents: 1_200_000,
            verifiedSavingsCents: -300_000,
            variancePercent: 33.3,
            varianceFlagged: true,
            assumptions: [],
        ));
    });

    app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    $alert = Alert::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->first();
    expect($alert)->not->toBeNull();
    expect($alert->type)->toBe(AlertType::VarianceFlag);
    expect($alert->title)->toBe("This period's verified savings differ from your assessment");
});

test('does not create an alert when variance is not flagged', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-02-01', 'UTC');
    seedVerificationPeriodData($organization, $periodStart, 100, 50, days: 31);

    $this->mock(VerificationCalculationService::class, function ($mock) {
        $mock->shouldReceive('calculate')->once()->andReturn(new VerificationCalculationResult(
            methodologyVersion: 'provisional-period-comparison-v1',
            isProvisional: true,
            baselineCostCents: 900_000,
            actualCostCents: 910_000,
            verifiedSavingsCents: -10_000,
            variancePercent: 1.1,
            varianceFlagged: false,
            assumptions: [],
        ));
    });

    app(RunVerificationAction::class)->execute($periodStart, $periodEnd);

    expect(Alert::query()->where('organization_id', $organization->id)->count())->toBe(0);
});
