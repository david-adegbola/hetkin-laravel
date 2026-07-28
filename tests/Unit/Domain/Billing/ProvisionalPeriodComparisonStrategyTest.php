<?php

use App\Domain\Billing\DTOs\LockedBaselineSummary;
use App\Domain\Billing\DTOs\MeterReadingPoint;
use App\Domain\Billing\DTOs\PricePoint;
use App\Domain\Billing\DTOs\VerificationCalculationInput;
use App\Domain\Billing\Exceptions\InsufficientVerificationDataException;
use App\Domain\Billing\Strategies\ProvisionalPeriodComparisonStrategy;
use Illuminate\Support\Carbon;

/**
 * Mirrors provisional-period-comparison-strategy.test.ts test-for-test —
 * the calculation that eventually backs an invoice, per the source's own
 * comment "deliberately the last thing standing between a computed number
 * and a customer's bill (shadow-mode human review), but the math itself
 * still has to be provably correct."
 */
function verificationHour(int $dayOffset, int $h): Carbon
{
    return Carbon::parse('2026-01-01', 'UTC')->addDays($dayOffset)->addHours($h);
}

/**
 * @return array{readings: array<int, MeterReadingPoint>, prices: array<int, PricePoint>}
 */
function buildVerificationPeriod(int $days, float $consumptionKwh, float $priceEurPerMwh): array
{
    $readings = [];
    $prices = [];
    for ($d = 0; $d < $days; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $readings[] = new MeterReadingPoint(verificationHour($d, $h), $consumptionKwh);
            $prices[] = new PricePoint(verificationHour($d, $h), $priceEurPerMwh);
        }
    }

    return ['readings' => $readings, 'prices' => $prices];
}

// 10,950,000 = 365 * 30,000 exactly, chosen so period-scaling stays exact
// for the 30-day test cases below.
function verificationBaseline(?int $latestOptimizedAnnualCostCents = 8_190_600): LockedBaselineSummary
{
    return new LockedBaselineSummary(
        id: 'baseline_1',
        methodologyVersion: 'provisional-heuristic-v1',
        baselineAnnualCostCents: 10_950_000,
        latestOptimizedAnnualCostCents: $latestOptimizedAnnualCostCents,
    );
}

test('always reports isProvisional = true and a stable methodology version', function () {
    $strategy = new ProvisionalPeriodComparisonStrategy;
    expect($strategy->isProvisional())->toBeTrue();
    expect($strategy->methodologyVersion())->toBe('provisional-period-comparison-v1');
});

test('throws InsufficientVerificationDataException with fewer than 5 days of matched data', function () {
    $data = buildVerificationPeriod(4, 100, 50);
    $strategy = new ProvisionalPeriodComparisonStrategy;

    expect(fn () => $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: verificationBaseline(),
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour(4, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    )))->toThrow(InsufficientVerificationDataException::class);
});

test('computes verified savings as baselineCostCents minus actualCostCents, scaled by period length', function () {
    $days = 30;
    $data = buildVerificationPeriod($days, 100, 50);
    $baseline = verificationBaseline();
    $strategy = new ProvisionalPeriodComparisonStrategy;
    $result = $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: $baseline,
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour($days, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    ));

    // actualCost = 100 kWh * 50 EUR/MWh / 1000 * 24 hours * 30 days = 3600 EUR = 360,000 cents
    $expectedActualCostCents = 360_000;
    $periodFraction = $days / 365;
    $expectedBaselineCostCents = (int) round($baseline->baselineAnnualCostCents * $periodFraction);

    expect($result->actualCostCents)->toBe($expectedActualCostCents);
    expect($result->baselineCostCents)->toBe($expectedBaselineCostCents);
    expect($result->verifiedSavingsCents)->toBe($expectedBaselineCostCents - $expectedActualCostCents);
});

test('flags variance when actual cost drifts more than 20% from the last assessment\'s expectation', function () {
    $days = 30;
    $data = buildVerificationPeriod($days, 100, 500); // much more expensive than baseline assumed
    $strategy = new ProvisionalPeriodComparisonStrategy;
    $result = $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: verificationBaseline(),
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour($days, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    ));

    expect($result->varianceFlagged)->toBeTrue();
    expect(abs($result->variancePercent))->toBeGreaterThan(20);
});

test('does not flag variance for a period that closely matches the prior expectation', function () {
    // expectedCostCents for 30 days = round(8,190,600 * 30/365) = 673,200.
    // 170 kWh/hour * 50 EUR/MWh over 720 hours = 6120 EUR = 612,000 cents,
    // which is ~9% under expectation - comfortably inside the 20% threshold.
    $days = 30;
    $data = buildVerificationPeriod($days, 170, 50);
    $strategy = new ProvisionalPeriodComparisonStrategy;
    $result = $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: verificationBaseline(),
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour($days, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    ));

    expect(abs($result->variancePercent))->toBeLessThan(20);
    expect($result->varianceFlagged)->toBeFalse();
});

test('flags variance for review when there is no prior savings assessment to compare against', function () {
    $days = 30;
    $data = buildVerificationPeriod($days, 100, 50);
    $strategy = new ProvisionalPeriodComparisonStrategy;
    $result = $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: verificationBaseline(null),
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour($days, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    ));

    // Deliberate design choice in the strategy: no expectation to compare
    // against is itself flagged, rather than silently reporting zero variance.
    expect($result->varianceFlagged)->toBeTrue();
    expect($result->variancePercent)->toBe(0.0);
});

test('never reports verified savings greater than the baseline cost (non-negative actual cost)', function () {
    $days = 30;
    $data = buildVerificationPeriod($days, 0, 50); // zero consumption -> zero actual cost
    $strategy = new ProvisionalPeriodComparisonStrategy;
    $result = $strategy->calculate(new VerificationCalculationInput(
        organizationId: 'org_1',
        baseline: verificationBaseline(),
        periodStart: verificationHour(0, 0),
        periodEnd: verificationHour($days, 0),
        readings: $data['readings'],
        prices: $data['prices'],
    ));

    expect($result->actualCostCents)->toBe(0);
    expect($result->verifiedSavingsCents)->toBe($result->baselineCostCents);
    expect($result->verifiedSavingsCents)->toBeLessThanOrEqual($result->baselineCostCents);
});
