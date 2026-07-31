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
use App\Domain\Pilots\Models\PilotApplication;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
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
    expect($vm['methodology']['assumptions'])->toBe([['Share of load assumed shiftable', '30,0%']]);
    expect($vm['customer_overview']['metering_points'][0]['label'])->toBe('Main meter');
    expect($vm['appendix']['glossary'])->toHaveCount(8);
});

/*
 * Regression coverage for a real reported bug: the executive-summary
 * waterfall's "Hetkin's service fee" row displayed the same figure as
 * "Net customer savings" — both showed the post-fee running total
 * (cumulative_cents) rather than the fee itself (abs(delta_cents)).
 * The underlying $waterfall array was always correct; the bug was in
 * resources/views/pdf/partials/waterfall.blade.php's value column, so
 * this renders the actual partial rather than only asserting on the PHP
 * array, which would not have caught the original bug.
 */
test('the waterfall partial renders the fee row as the fee amount, not the net-savings figure', function () {
    $waterfall = [
        ['label' => 'Total projected savings', 'delta_cents' => 377795, 'cumulative_cents' => 377795, 'kind' => 'start'],
        ['label' => "Hetkin's service fee", 'delta_cents' => -75559, 'cumulative_cents' => 302236, 'kind' => 'decrease'],
        ['label' => 'Net customer savings', 'delta_cents' => 0, 'cumulative_cents' => 302236, 'kind' => 'end'],
    ];

    $html = view('pdf.partials.waterfall', ['waterfall' => $waterfall])->render();

    $feeAmount = Money::formatEurCents(75559);
    $netAmount = Money::formatEurCents(302236);

    // The fee row's own cell must show the fee (755,59 €), and must NOT
    // show the net-savings figure that the original bug displayed there
    // instead.
    // "service fee" (no apostrophe) rather than "Hetkin's service fee" —
    // Blade's {{ }} HTML-escapes the label, so the literal apostrophe
    // doesn't survive as-is in the rendered output.
    $feeRowPos = strpos($html, 'service fee');
    $feeRowHtml = substr($html, $feeRowPos, strpos($html, '</tr>', $feeRowPos) - $feeRowPos);
    expect($feeRowHtml)->toContain($feeAmount);
    expect($feeRowHtml)->not->toContain($netAmount);

    $netRowPos = strpos($html, 'Net customer savings');
    $netRowHtml = substr($html, $netRowPos, strpos($html, '</tr>', $netRowPos) - $netRowPos);
    expect($netRowHtml)->toContain($netAmount);
});

test('fee-related figures are consistent everywhere when a real fee is configured', function () {
    $organization = Organization::factory()->create(['revenue_share_percent' => 20.00]);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'estimated_annual_savings_cents' => 377795,
        'hetkin_share_cents' => 75559,
        'net_customer_savings_cents' => 302236,
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['executive_summary']['fee_configured'])->toBeTrue();
    expect($vm['savings']['fee_configured'])->toBeTrue();
    expect($vm['executive_summary']['roi_label'])->not->toBe('Fee not yet configured');
    expect($vm['savings']['table'])->toContain(["Hetkin's service fee", Money::formatEurCents(75559)]);
    expect($vm['savings']['table'])->toContain(['Net customer savings', Money::formatEurCents(302236)]);
});

test('configured fee reports derive net savings from total savings minus the fee', function () {
    $organization = Organization::factory()->create(['revenue_share_percent' => 20.00]);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'estimated_annual_savings_cents' => 100_000,
        'hetkin_share_cents' => 20_000,
        // Deliberately stale: reports must never repeat an internally
        // contradictory persisted net value.
        'net_customer_savings_cents' => 1,
    ]);

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['savings']['waterfall'][2]['cumulative_cents'])->toBe(80_000);
    expect($vm['savings']['table'])->toContain(['Net customer savings', Money::formatEurCents(80_000)]);
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

/*
 * Issue 2/3: the headline savings figure is computed purely from raw
 * meter readings + prices (ProvisionalHeuristicStrategy) — it never
 * actually depends on whether the customer's load has been confirmed
 * flexible. With no schedule data on file at all, the report must not
 * present that figure with full confidence.
 */
test('an assessment with no confirmed schedule data shows illustrative, low-confidence KPIs', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['executive_summary']['production_profile_confirmed'])->toBeFalse();
    expect($vm['executive_summary']['confidence_note'])->not->toBeNull();
    expect($vm['cover']['report_title'])->toBe('Savings Diagnostic');
    $annualSavingsKpi = collect($vm['executive_summary']['kpis'])->firstWhere('label', 'Annual savings (illustrative)');
    expect($annualSavingsKpi)->not->toBeNull();
    expect($annualSavingsKpi['tone'])->toBe('neutral');
    expect($vm['customer_overview']['production_data_status'])
        ->toBe('Not yet confirmed — no Unmanned schedule block with a known energy use is on file yet.');
});

test('an assessment with a confirmed eligible schedule block shows full-confidence KPIs', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['executive_summary']['production_profile_confirmed'])->toBeTrue();
    expect($vm['executive_summary']['confidence_note'])->toBeNull();
    $annualSavingsKpi = collect($vm['executive_summary']['kpis'])->firstWhere('label', 'Annual savings');
    expect($annualSavingsKpi)->not->toBeNull();
    expect($annualSavingsKpi['tone'])->toBe('cheap');
    expect($vm['customer_overview']['production_data_status'])
        ->toBe('Confirmed — at least one Unmanned schedule block with a known energy use is on file.');
});

test('the executive summary always states its assumptions and uses Finnish decimal formatting', function () {
    $organization = Organization::factory()->create(['revenue_share_percent' => 20.00]);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create([
        'assumptions' => ['flexPct' => 0.3, 'cheapHoursPerDay' => 3, 'annualizeFactor' => 52.14, 'sampleDayCount' => 7],
    ]);
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create([
        'current_annual_cost_cents' => 1_000_000,
        'estimated_annual_savings_cents' => 123_456,
        'hetkin_share_cents' => 20_000,
    ]);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['executive_summary']['confidence_note'])->toBeNull();
    expect($vm['executive_summary']['assumption_summary'])
        ->toBe('Estimate assumes 30% of load can move to the 3 cheapest hours each day.');
    expect(collect($vm['executive_summary']['kpis'])->firstWhere('label', 'Avg. cost reduction')['value'])
        ->toBe('12,3%');
    expect($vm['executive_summary']['roi_label'])->toStartWith('5,2x');
    expect($vm['savings']['table'])->toContain(['Savings percentage', '12,3%']);
});

test('production_profile reads from the pilot application win_condition when present, independent of confirmation status', function () {
    $pilotApplication = PilotApplication::factory()->create(['win_condition' => 'Cut peak-hour freezer costs by 20%.']);
    $organization = Organization::factory()->create(['pilot_application_id' => $pilotApplication->id]);
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();

    $data = app(SavingsReportLoader::class)->load($assessment->id, $organization->id);
    $vm = SavingsReportViewModelBuilder::build($data);

    expect($vm['customer_overview']['production_profile'])->toBe('Cut peak-hour freezer costs by 20%.');
});
