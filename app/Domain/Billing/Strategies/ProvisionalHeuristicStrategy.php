<?php

namespace App\Domain\Billing\Strategies;

use App\Domain\Billing\Contracts\SavingsCalculationService;
use App\Domain\Billing\DTOs\MeterReadingPoint;
use App\Domain\Billing\DTOs\PricePoint;
use App\Domain\Billing\DTOs\SavingsCalculationInput;
use App\Domain\Billing\DTOs\SavingsCalculationResult;
use App\Domain\Billing\Exceptions\InsufficientDataException;
use Illuminate\Support\Carbon;

/**
 * Line-for-line port of the Next.js app's `ProvisionalHeuristicStrategy`
 * (src/lib/savings/strategies/provisional-heuristic-strategy.ts).
 *
 * "v1 constrained heuristic, not a general solver" — shift a fixed
 * percentage of each hour's load into that day's cheapest hours. This is
 * NOT a methodology that has been through legal/energy-market review;
 * `isProvisional = true` reflects that explicitly — every caller must
 * treat this as an estimate, never as a number to lock into a customer
 * contract or invoice.
 *
 * Day/hour bucketing is explicitly UTC (`->copy()->utc()->format('Y-m-d')`)
 * regardless of app timezone config, matching JS's
 * `toISOString().slice(0,10)` — same reasoning as
 * App\Domain\Optimization\Strategies\ProvisionalWindowShiftStrategy
 * (MIGRATION_PLAN.md ADR-38's predecessor). Reading/price matching uses a
 * UTC unix-timestamp key rather than v2's ISO-string equality check —
 * behaviorally identical (both are just "same instant"), simpler to get
 * exactly right in PHP.
 */
class ProvisionalHeuristicStrategy implements SavingsCalculationService
{
    private const FLEX_PCT = 0.3;

    private const CHEAP_HOURS_PER_DAY = 3;

    private const METHODOLOGY_VERSION = 'provisional-heuristic-v1';

    public function methodologyVersion(): string
    {
        return self::METHODOLOGY_VERSION;
    }

    public function isProvisional(): bool
    {
        return true;
    }

    /**
     * @throws InsufficientDataException
     */
    public function calculate(SavingsCalculationInput $input): SavingsCalculationResult
    {
        $matched = $this->matchReadingsToPrices($input->readings, $input->prices);

        $byDay = [];
        foreach ($matched as $row) {
            $byDay[$this->dayKey($row['hourStart'])][] = $row;
        }
        ksort($byDay);

        $totalActualCostEur = 0.0;
        $totalShiftedCostEur = 0.0;
        $usableDayCount = 0;

        foreach ($byDay as $rows) {
            if (count($rows) < 4) {
                continue; // too sparse to be meaningful for this day
            }
            $usableDayCount++;

            $actualCost = array_sum(array_map(
                fn (array $r) => $r['consumptionKwh'] * $r['eurPerMwh'] / 1000,
                $rows,
            ));
            $totalActualCostEur += $actualCost;

            $sortedByPrice = $rows;
            usort($sortedByPrice, fn (array $a, array $b) => $a['eurPerMwh'] <=> $b['eurPerMwh']);
            $cheapest = array_slice($sortedByPrice, 0, self::CHEAP_HOURS_PER_DAY);
            $cheapAvgPrice = array_sum(array_column($cheapest, 'eurPerMwh'))
                / min(self::CHEAP_HOURS_PER_DAY, count($sortedByPrice));

            $retainedCost = array_sum(array_map(
                fn (array $r) => $r['consumptionKwh'] * (1 - self::FLEX_PCT) * $r['eurPerMwh'] / 1000,
                $rows,
            ));
            $totalFlexKwh = array_sum(array_map(
                fn (array $r) => $r['consumptionKwh'] * self::FLEX_PCT,
                $rows,
            ));
            $flexCostShifted = $totalFlexKwh * $cheapAvgPrice / 1000;

            $totalShiftedCostEur += $retainedCost + $flexCostShifted;
        }

        if ($usableDayCount < 5) {
            throw new InsufficientDataException(
                "Only {$usableDayCount} usable day(s) of matched meter + price data — need at least 5 for even a provisional estimate.",
            );
        }

        $annualizeFactor = 365 / $usableDayCount;
        $currentAnnualCostCents = (int) round($totalActualCostEur * $annualizeFactor * 100);
        $optimizedAnnualCostCents = (int) round($totalShiftedCostEur * $annualizeFactor * 100);

        return new SavingsCalculationResult(
            methodologyVersion: self::METHODOLOGY_VERSION,
            isProvisional: true,
            currentAnnualCostCents: $currentAnnualCostCents,
            optimizedAnnualCostCents: $optimizedAnnualCostCents,
            estimatedAnnualSavingsCents: $currentAnnualCostCents - $optimizedAnnualCostCents,
            sampleDayCount: $usableDayCount,
            assumptions: [
                'flexPct' => self::FLEX_PCT,
                'cheapHoursPerDay' => self::CHEAP_HOURS_PER_DAY,
                'annualizeFactor' => $annualizeFactor,
                'sampleDayCount' => $usableDayCount,
                'note' => 'Provisional heuristic — not a legally-reviewed baseline methodology. See hetkin-phase1-business-discovery.md §3.',
            ],
        );
    }

    /**
     * @param  array<int, MeterReadingPoint>  $readings
     * @param  array<int, PricePoint>  $prices
     * @return array<int, array{hourStart: Carbon, consumptionKwh: float, eurPerMwh: float}>
     */
    private function matchReadingsToPrices(array $readings, array $prices): array
    {
        $priceMap = [];
        foreach ($prices as $price) {
            $priceMap[$this->timeKey($price->hourStart)] = $price->eurPerMwh;
        }

        $matched = [];
        foreach ($readings as $reading) {
            $key = $this->timeKey($reading->hourStart);
            if (array_key_exists($key, $priceMap)) {
                $matched[] = [
                    'hourStart' => $reading->hourStart,
                    'consumptionKwh' => $reading->consumptionKwh,
                    'eurPerMwh' => $priceMap[$key],
                ];
            }
        }

        return $matched;
    }

    private function timeKey(Carbon $d): int
    {
        return $d->copy()->utc()->timestamp;
    }

    private function dayKey(Carbon $d): string
    {
        return $d->copy()->utc()->format('Y-m-d');
    }
}
