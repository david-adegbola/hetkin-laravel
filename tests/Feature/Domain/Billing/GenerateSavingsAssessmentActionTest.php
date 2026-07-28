<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Actions\GenerateSavingsAssessmentAction;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Models\User;
use Illuminate\Support\Carbon;

function seedFlatSavingsWeek(Organization $organization, float $consumptionKwh, float $priceEurPerMwh, int $days = 5): MeteringPoint
{
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();

    for ($d = 0; $d < $days; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = Carbon::parse('2026-01-01', 'UTC')->addDays($d)->addHours($h);
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

    return $point;
}

test('throws when no meter data has been uploaded', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    expect(fn () => app(GenerateSavingsAssessmentAction::class)->execute($organization, $user))
        ->toThrow(BillingActionException::class, 'No meter data uploaded yet');
});

test('throws when no price data covers the meter data\'s date range', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    MeterReading::factory()->for($point, 'meteringPoint')->create([
        'hour_start' => Carbon::parse('2026-01-01 00:00', 'UTC'),
    ]);

    expect(fn () => app(GenerateSavingsAssessmentAction::class)->execute($organization, $user))
        ->toThrow(BillingActionException::class, 'No electricity price data covers');
});

test('throws (wrapped) when there is not enough matched data for even a provisional estimate', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    seedFlatSavingsWeek($organization, 100, 50, days: 2);

    expect(fn () => app(GenerateSavingsAssessmentAction::class)->execute($organization, $user))
        ->toThrow(BillingActionException::class);
});

test('creates a DRAFT baseline and savings assessment with the golden-dataset figures', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $prices = array_map(fn ($h) => ($h + 1) * 10, range(0, 23));
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    for ($d = 0; $d < 5; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = Carbon::parse('2026-01-01', 'UTC')->addDays($d)->addHours($h);
            MeterReading::factory()->for($point, 'meteringPoint')->create([
                'hour_start' => $hourStart,
                'consumption_kwh' => 100,
            ]);
            ElectricityPrice::factory()->create([
                'bidding_zone' => BiddingZone::FI,
                'hour_start' => $hourStart,
                'eur_per_mwh' => $prices[$h],
                'source' => PriceSource::Entsoe,
            ]);
        }
    }

    $assessment = app(GenerateSavingsAssessmentAction::class)->execute($organization, $user);

    expect($assessment)->toBeInstanceOf(SavingsAssessment::class);
    expect($assessment->current_annual_cost_cents)->toBe(10_950_000);
    expect($assessment->optimized_annual_cost_cents)->toBe(8_190_600);
    expect($assessment->estimated_annual_savings_cents)->toBe(2_759_400);
    expect($assessment->hetkin_share_cents)->toBe(0);
    expect($assessment->net_customer_savings_cents)->toBe(2_759_400);
    expect($assessment->is_provisional)->toBeTrue();

    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::query()->findOrFail($assessment->baseline_id);
    expect($baseline->status)->toBe(BaselineStatus::Draft);
    expect($baseline->baseline_annual_cost_cents)->toBe(10_950_000);
});

test('splits savings between the customer and Hetkin using the organization\'s revenue share', function () {
    $organization = Organization::factory()->create(['revenue_share_percent' => 10]);
    $user = User::factory()->create();
    $prices = array_map(fn ($h) => ($h + 1) * 10, range(0, 23));
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    for ($d = 0; $d < 5; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = Carbon::parse('2026-01-01', 'UTC')->addDays($d)->addHours($h);
            MeterReading::factory()->for($point, 'meteringPoint')->create([
                'hour_start' => $hourStart,
                'consumption_kwh' => 100,
            ]);
            ElectricityPrice::factory()->create([
                'bidding_zone' => BiddingZone::FI,
                'hour_start' => $hourStart,
                'eur_per_mwh' => $prices[$h],
                'source' => PriceSource::Entsoe,
            ]);
        }
    }

    $assessment = app(GenerateSavingsAssessmentAction::class)->execute($organization, $user);

    // estimatedAnnualSavingsCents = 2,759,400; 10% share = round(275,940) = 275,940
    expect($assessment->hetkin_share_cents)->toBe(275_940);
    expect($assessment->net_customer_savings_cents)->toBe(2_483_460);
});

test('creates a new DRAFT baseline every time it runs, even with an existing LOCKED one', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create();
    seedFlatSavingsWeek($organization, 100, 50);

    app(GenerateSavingsAssessmentAction::class)->execute($organization, $user);

    expect(Baseline::query()->count())->toBe(2);
    expect(Baseline::query()->where('status', BaselineStatus::Draft)->count())->toBe(1);
});

test('writes an audit log entry for the generated assessment', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    seedFlatSavingsWeek($organization, 100, 50);

    $assessment = app(GenerateSavingsAssessmentAction::class)->execute($organization, $user);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('savings_assessment.generated');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->entity_id)->toBe($assessment->id);
});
