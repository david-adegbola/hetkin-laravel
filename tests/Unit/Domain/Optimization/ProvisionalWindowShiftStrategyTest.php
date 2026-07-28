<?php

use App\Domain\Optimization\DTOs\PricePoint;
use App\Domain\Optimization\DTOs\RecommendationEngineInput;
use App\Domain\Optimization\DTOs\ScheduleBlockInput;
use App\Domain\Optimization\Enums\DeliveryRisk;
use App\Domain\Optimization\Strategies\ProvisionalWindowShiftStrategy;
use Illuminate\Support\Carbon;

/**
 * Mirrors src/lib/optimization/strategies/provisional-window-shift-strategy.test.ts
 * test-for-test — covers the filters that keep a recommendation from ever
 * being worse, negligible, or moved outside the same day.
 */
function hourAt(int $dayOffset, int $h): Carbon
{
    return Carbon::createFromTimestampUTC(0)->addDays($dayOffset)->addHours($h);
}

/**
 * @return array<int, PricePoint>
 */
function dayPricesFixture(int $dayOffset, array $prices): array
{
    return array_map(
        fn (int $h, float $eurPerMwh) => new PricePoint(hourAt($dayOffset, $h), $eurPerMwh),
        array_keys($prices),
        array_values($prices),
    );
}

function blockFixture(array $overrides = []): ScheduleBlockInput
{
    return new ScheduleBlockInput(
        id: $overrides['id'],
        assetLabel: $overrides['assetLabel'] ?? 'Line A',
        startAt: $overrides['startAt'] ?? hourAt(0, 12),
        endAt: $overrides['endAt'] ?? hourAt(0, 13),
        estimatedKwh: array_key_exists('estimatedKwh', $overrides) ? $overrides['estimatedKwh'] : 500.0,
    );
}

test('always reports isProvisional true and a stable methodology version', function () {
    $strategy = new ProvisionalWindowShiftStrategy;

    expect($strategy->isProvisional())->toBeTrue();
    expect($strategy->methodologyVersion())->toBe('provisional-window-shift-v1');
});

test('recommends shifting a block into a materially cheaper same-day window', function () {
    $prices = array_map(fn ($h) => $h === 3 ? 10.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: [blockFixture(['id' => 'block_1'])],
        prices: dayPricesFixture(0, $prices),
    ));

    expect($result->recommendations)->toHaveCount(1);
    $rec = $result->recommendations[0];
    expect($rec->scheduleBlockId)->toBe('block_1');
    expect((int) $rec->proposedStartAt->copy()->utc()->format('G'))->toBe(3);
    // (100 - 10) EUR/MWh * 500 kWh / 1000 = 45 EUR = 4500 cents
    expect($rec->projectedSavingsCents)->toBe(4500);
    expect($rec->deliveryRisk)->toBe(DeliveryRisk::High); // |12 - 3| = 9 hours > 8
});

test('does not recommend a shift when the block is already in the cheapest window', function () {
    $prices = array_map(fn ($h) => $h === 3 ? 10.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: [blockFixture(['id' => 'block_1', 'startAt' => hourAt(0, 3), 'endAt' => hourAt(0, 4)])],
        prices: dayPricesFixture(0, $prices),
    ));

    expect($result->recommendations)->toHaveCount(0);
});

test('skips blocks with no estimated energy draw', function () {
    $prices = array_map(fn ($h) => $h === 3 ? 10.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: [blockFixture(['id' => 'block_1', 'estimatedKwh' => null])],
        prices: dayPricesFixture(0, $prices),
    ));

    expect($result->recommendations)->toHaveCount(0);
});

test('does not propose a shift below the minimum improvement threshold (5%)', function () {
    // Best available window is only ~2% cheaper - below the floor.
    $prices = array_map(fn ($h) => $h === 3 ? 98.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: [blockFixture(['id' => 'block_1'])],
        prices: dayPricesFixture(0, $prices),
    ));

    expect($result->recommendations)->toHaveCount(0);
});

test('does not propose a shift below the minimum savings floor (€1) even at a huge % improvement', function () {
    // (100 - 1) EUR/MWh * 1 kWh / 1000 ~= 0.099 EUR, rounds to 10 cents - well under the €1 floor.
    $prices = array_map(fn ($h) => $h === 3 ? 1.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: [blockFixture(['id' => 'block_1', 'estimatedKwh' => 1.0])],
        prices: dayPricesFixture(0, $prices),
    ));

    expect($result->recommendations)->toHaveCount(0);
});

test('ignores a day with fewer price points than the block\'s duration', function () {
    $prices = array_map(fn ($h) => $h === 3 ? 10.0 : 100.0, range(0, 23));
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        // 10-hour block, but only 5 price points supplied for that day.
        blocks: [blockFixture(['id' => 'block_1', 'startAt' => hourAt(0, 0), 'endAt' => hourAt(0, 10)])],
        prices: array_slice(dayPricesFixture(0, $prices), 0, 5),
    ));

    expect($result->recommendations)->toHaveCount(0);
});

test('keeps proposed windows well-formed: same duration, same day, valid risk tier', function () {
    $prices = [
        40, 55, 20, 90, 15, 60, 70, 25, 80, 10, 45, 65, 30, 50, 75, 35, 85, 5, 95, 100, 12, 22, 33, 44,
    ];
    $blocks = array_map(
        fn (int $i) => blockFixture([
            'id' => "block_{$i}",
            'startAt' => hourAt(0, $i),
            'endAt' => hourAt(0, $i + 2),
            'estimatedKwh' => 300.0 + $i * 10,
        ]),
        range(0, 9),
    );
    $strategy = new ProvisionalWindowShiftStrategy;

    $result = $strategy->generate(new RecommendationEngineInput(
        organizationId: 'org_1',
        scheduleId: 'sched_1',
        blocks: $blocks,
        prices: dayPricesFixture(0, $prices),
    ));

    expect(count($result->recommendations))->toBeGreaterThan(0);
    foreach ($result->recommendations as $rec) {
        $durationMs = $rec->proposedStartAt->diffInMilliseconds($rec->proposedEndAt);
        expect((int) $durationMs)->toBe(2 * 60 * 60 * 1000);
        expect($rec->projectedSavingsCents)->toBeGreaterThanOrEqual(100);
        expect($rec->deliveryRisk)->toBeIn([DeliveryRisk::Low, DeliveryRisk::Medium, DeliveryRisk::High]);
        expect($rec->proposedStartAt->copy()->utc()->format('Y-m-d'))->toBe('1970-01-01');
    }
});
