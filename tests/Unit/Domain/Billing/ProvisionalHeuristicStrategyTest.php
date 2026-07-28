<?php

use App\Domain\Billing\DTOs\MeterReadingPoint;
use App\Domain\Billing\DTOs\PricePoint;
use App\Domain\Billing\DTOs\SavingsCalculationInput;
use App\Domain\Billing\Exceptions\InsufficientDataException;
use App\Domain\Billing\Strategies\ProvisionalHeuristicStrategy;
use Illuminate\Support\Carbon;

/**
 * Golden-dataset and edge-case tests for the savings/baseline calculation
 * engine — mirrors provisional-heuristic-strategy.test.ts test-for-test.
 * Per the source's own comment, this is "the one part of the codebase
 * where 'well-tested' isn't a best practice, it's the actual product being
 * sold."
 */
function billingHour(int $dayOffset, int $h): Carbon
{
    return Carbon::parse('2026-01-01', 'UTC')->addDays($dayOffset)->addHours($h);
}

/** price[h] = (h+1) * 10 EUR/MWh -> 10, 20, ..., 240 */
function flatDayPrices(): array
{
    return array_map(fn ($h) => ($h + 1) * 10, range(0, 23));
}

/**
 * @return array{readings: array<int, MeterReadingPoint>, prices: array<int, PricePoint>}
 */
function buildSavingsDays(int $dayCount, float $consumptionKwh, array $prices): array
{
    $readings = [];
    $pricePoints = [];
    for ($d = 0; $d < $dayCount; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $readings[] = new MeterReadingPoint(billingHour($d, $h), $consumptionKwh);
            $pricePoints[] = new PricePoint(billingHour($d, $h), $prices[$h] ?? 0);
        }
    }

    return ['readings' => $readings, 'prices' => $pricePoints];
}

test('always reports isProvisional = true and a stable methodology version', function () {
    $strategy = new ProvisionalHeuristicStrategy;
    expect($strategy->isProvisional())->toBeTrue();
    expect($strategy->methodologyVersion())->toBe('provisional-heuristic-v1');
});

test('matches a hand-computed golden dataset exactly', function () {
    // 5 identical days, flat 100 kWh/hour, prices 10..240 EUR/MWh step 10.
    //   cheapest 3 hours: 10, 20, 30 -> cheapAvgPrice = 20
    //   actualCost/day = sum(price) * 0.1 = 3000 * 0.1 = 300 EUR
    //   retainedCost/day = 0.7 * 300 = 210 EUR
    //   totalFlexKwh/day = 24 * 100 * 0.3 = 720 kWh
    //   flexCostShifted/day = 720 * 20 / 1000 = 14.4 EUR
    //   shiftedCost/day = 210 + 14.4 = 224.4 EUR
    //   over 5 days: totalActual = 1500 EUR, totalShifted = 1122 EUR
    //   annualizeFactor = 365 / 5 = 73
    //   currentAnnualCostCents    = round(1500 * 73 * 100) = 10,950,000
    //   optimizedAnnualCostCents  = round(1122 * 73 * 100) =  8,190,600
    //   estimatedAnnualSavingsCents = 2,759,400
    $data = buildSavingsDays(5, 100, flatDayPrices());
    $strategy = new ProvisionalHeuristicStrategy;
    $result = $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices']));

    expect($result->sampleDayCount)->toBe(5);
    expect($result->currentAnnualCostCents)->toBe(10_950_000);
    expect($result->optimizedAnnualCostCents)->toBe(8_190_600);
    expect($result->estimatedAnnualSavingsCents)->toBe(2_759_400);
    expect($result->isProvisional)->toBeTrue();
    expect($result->methodologyVersion)->toBe('provisional-heuristic-v1');
});

test('throws InsufficientDataException with fewer than 5 usable days', function () {
    $data = buildSavingsDays(4, 100, flatDayPrices());
    $strategy = new ProvisionalHeuristicStrategy;

    expect(fn () => $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices'])))
        ->toThrow(InsufficientDataException::class);
});

test('does not count a sparse day (fewer than 4 matched hours) toward usable days', function () {
    $data = buildSavingsDays(5, 100, flatDayPrices());
    $sparseDay = 5;
    $data['readings'][] = new MeterReadingPoint(billingHour($sparseDay, 0), 100);
    $data['readings'][] = new MeterReadingPoint(billingHour($sparseDay, 1), 100);
    $data['prices'][] = new PricePoint(billingHour($sparseDay, 0), 10);
    $data['prices'][] = new PricePoint(billingHour($sparseDay, 1), 20);

    $strategy = new ProvisionalHeuristicStrategy;
    $result = $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices']));

    expect($result->sampleDayCount)->toBe(5);
    expect($result->currentAnnualCostCents)->toBe(10_950_000);
});

test('drops readings with no matching price instead of miscounting them', function () {
    $data = buildSavingsDays(5, 100, flatDayPrices());
    $data['readings'][] = new MeterReadingPoint(billingHour(10, 0), 999);

    $strategy = new ProvisionalHeuristicStrategy;
    $result = $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices']));

    expect($result->currentAnnualCostCents)->toBe(10_950_000);
});

test('reports zero savings, never negative, when prices are genuinely flat', function () {
    $flat = array_fill(0, 24, 50);
    $data = buildSavingsDays(5, 100, $flat);

    $strategy = new ProvisionalHeuristicStrategy;
    $result = $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices']));

    expect($result->estimatedAnnualSavingsCents)->toBe(0);
});

test('stamps every result with a provisional disclaimer in assumptions', function () {
    $data = buildSavingsDays(5, 100, flatDayPrices());
    $strategy = new ProvisionalHeuristicStrategy;
    $result = $strategy->calculate(new SavingsCalculationInput('org_1', $data['readings'], $data['prices']));

    expect(strtolower($result->assumptions['note']))->toContain('provisional');
});
