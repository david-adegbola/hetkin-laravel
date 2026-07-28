<?php

use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Reports\Money;
use App\Domain\Billing\Reports\SavingsReportLoader;
use App\Domain\Billing\Reports\SavingsReportViewModelBuilder;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use Illuminate\Support\Carbon;

test('the loader returns null for an assessment belonging to another organization', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $baseline = Baseline::factory()->for($other, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($other, 'organization')->for($baseline)->create();

    $result = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);

    expect($result)->toBeNull();
});

test('the loader and view-model builder compute a coherent report for a real assessment', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Oy']);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->locked()->for($organization, 'organization')->create([
        'assumptions' => ['flexPct' => 0.3, 'note' => 'should be excluded'],
    ]);
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $periodEnd = Carbon::parse('2026-01-06', 'UTC');
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'current_annual_cost_cents' => 1_000_000,
        'optimized_annual_cost_cents' => 800_000,
        'estimated_annual_savings_cents' => 200_000,
        'hetkin_share_cents' => 20_000,
        'net_customer_savings_cents' => 180_000,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
    ]);

    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create(['label' => 'Main meter']);
    for ($h = 0; $h < 24; $h++) {
        $hourStart = $periodStart->copy()->addHours($h);
        MeterReading::factory()->for($point, 'meteringPoint')->create([
            'hour_start' => $hourStart,
            'consumption_kwh' => 100,
        ]);
        ElectricityPrice::factory()->create([
            'bidding_zone' => BiddingZone::FI,
            'hour_start' => $hourStart,
            'eur_per_mwh' => 10 + $h,
            'source' => PriceSource::Entsoe,
        ]);
    }

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    expect($data)->not->toBeNull();
    expect($data->versionNumber)->toBe(1);

    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['cover']['organization_name'])->toBe('Acme Oy');
    expect($vm['cover']['baseline_status'])->toBe(BaselineStatus::Locked->value);
    expect($vm['savings']['waterfall'][0]['cumulative_cents'])->toBe(200_000);
    expect($vm['savings']['waterfall'][2]['cumulative_cents'])->toBe(180_000);
    expect($vm['savings']['table'][2])->toBe(['Total projected savings', Money::formatEurCents(200_000)]);
    expect($vm['baseline']['avg_load_profile'])->toHaveCount(24);
    expect($vm['market']['heatmap'])->toHaveCount(24);
    expect($vm['methodology']['assumptions'])->toBe([['Flex Pct', '0.3000']]);
    expect($vm['customer_overview']['metering_points'][0]['label'])->toBe('Main meter');
    expect($vm['appendix']['glossary'])->toHaveCount(7);
});

test('the view-model handles an assessment with no schedule, recommendations, or readings gracefully', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'period_start' => null,
        'period_end' => null,
        'hetkin_share_cents' => 0,
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['cover']['period_label'])->toBe('Period not recorded');
    expect($vm['schedule']['current'])->toBe([]);
    expect($vm['schedule']['callouts'])->toBe(['No schedule recommendations available yet — run the optimization engine against an active production schedule.']);
    expect($vm['executive_summary']['roi_label'])->toBe('Fee not yet configured');
});
