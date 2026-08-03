<?php

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
use Illuminate\Support\Carbon;

test('the loader returns null for an assessment belonging to another organization', function () {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    app(CurrentOrganization::class)->set($other);
    $baseline = Baseline::factory()->for($other, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($other, 'organization')->for($baseline)->create();

    expect(app(SavingsReportLoader::class)->load($assessment->id, $organization->id))->toBeNull();
});

test('the customer report is a compact energy-price decision report', function () {
    $organization = Organization::factory()->create(['name' => 'Acme Oy']);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create([
        'assumptions' => ['flexPct' => 0.3, 'cheapHoursPerDay' => 3],
    ]);
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'current_annual_cost_cents' => 1_000_000,
        'estimated_annual_savings_cents' => 200_000,
        'period_start' => $periodStart,
        'period_end' => $periodStart->copy()->addDays(4),
        'sample_day_count' => 5,
    ]);
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    MeterReading::factory()->for($point, 'meteringPoint')->create(['hour_start' => $periodStart]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['cover']['report_title'])->toBe('Acme Oy Savings Assessment');
    expect($vm['executive_summary']['kpis'])->toContain([
        'label' => 'Estimated annual energy-price saving',
        'value' => Money::formatEurCents(200_000),
        'tone' => 'cheap',
    ]);
    expect($vm['calculation']['data_used'])->toBe('5 days of metered data were used for this estimate.');
    expect($vm['calculation']['operational_assumption'])
        ->toBe('The estimate assumes 30% of eligible electricity use can move to the 3 cheapest hours each day.');
    expect($vm['calculation']['confirmation_note'])->toContain('network tariff and capacity charges');
    expect($vm)->not->toHaveKeys(['customer_overview', 'baseline', 'market', 'savings', 'schedule']);
});

test('net saving is only shown when a Hetkin fee is configured', function () {
    $organization = Organization::factory()->create(['revenue_share_percent' => 20.00]);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'estimated_annual_savings_cents' => 100_000,
        'hetkin_share_cents' => 20_000,
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['executive_summary']['kpis'])->toContain([
        'label' => 'Estimated annual net saving',
        'value' => Money::formatEurCents(80_000),
        'tone' => 'cheap',
    ]);
});

test('the customer copy turns legacy snapshots into the compact report and caps recommendations', function () {
    $organization = Organization::factory()->create(['name' => 'Legacy Oy']);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $recommendations = array_map(fn (int $index) => [
        'title' => "Change {$index}", 'rationale' => 'Move the load.', 'savings_label' => '10,00 € per occurrence', 'complexity' => 'LOW',
    ], range(1, 7));
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'report_snapshot' => [
            'cover' => ['organization_name' => 'Legacy Oy', 'generated_label' => '01/08/2026 · Methodology provisional-heuristic-v1'],
            'executive_summary' => [
                'fee_configured' => false,
                'kpis' => [
                    ['label' => 'Annual savings', 'value' => '100,00 €'],
                    ['label' => 'Monthly savings', 'value' => '8,33 €'],
                    ['label' => 'Avg. cost reduction', 'value' => '10,0%'],
                ],
                'assumption_summary' => 'Estimate assumes 30% of load can move to the 3 cheapest hours each day.',
            ],
            'customer_overview' => ['data_coverage' => ['day_count' => 5]],
            'recommendations' => $recommendations,
            'methodology' => ['strategy_label' => 'provisional-heuristic-v1'],
            'appendix' => ['version_history' => 'Version 1'],
        ],
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = app(SavingsReportLoader::class)->viewModel($data);

    expect($vm['recommendations']['items'])->toHaveCount(5);
    expect($vm['recommendations']['additional_count'])->toBe(2);
    expect($vm['calculation']['data_used'])->toBe('5 days of metered data were used for this estimate.');
    expect(json_encode($vm, JSON_THROW_ON_ERROR))->not->toMatch('/provisional|heuristic|methodology|coverage|annualization|ENTSO-E/i');
    expect($vm)->toHaveKeys(['cover', 'executive_summary', 'recommendations', 'calculation']);
});
