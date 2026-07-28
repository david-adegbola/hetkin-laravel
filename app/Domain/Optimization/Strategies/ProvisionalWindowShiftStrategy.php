<?php

namespace App\Domain\Optimization\Strategies;

use App\Domain\Optimization\Contracts\RecommendationEngine;
use App\Domain\Optimization\DTOs\RecommendationEngineInput;
use App\Domain\Optimization\DTOs\RecommendationEngineResult;
use App\Domain\Optimization\DTOs\RecommendationOutput;
use App\Domain\Optimization\Enums\DeliveryRisk;
use Illuminate\Support\Carbon;

/**
 * A line-for-line port of the Next.js app's
 * src/lib/optimization/strategies/provisional-window-shift-strategy.ts —
 * the v1 heuristic (Phase 5 M2: "constrained heuristic, not a general
 * solver"). For each schedule block with a known energy draw, looks
 * within the same calendar day for a contiguous window of the same
 * duration with a meaningfully cheaper average price, and proposes
 * shifting into it. Same-day only is deliberate: Phase 2's scheduler
 * persona fears "risking a delivery commitment," and a same-day shift is
 * the lowest-risk kind of recommendation to earn trust with before
 * anything more ambitious.
 *
 * `isProvisional() === true`: this hasn't been validated against real
 * production data or reviewed the way Phase 1 §3 wants the baseline
 * methodology reviewed. Treat every output as a suggestion, not a proof.
 *
 * All day/hour extraction happens in UTC explicitly (`->utc()` before
 * reading the hour or date), matching the source's `getUTCHours()`/
 * `toISOString().slice(0, 10)` — this must stay UTC-based regardless of
 * the app's configured timezone, or day/hour bucketing would silently
 * diverge from the original for any input not already in UTC.
 */
class ProvisionalWindowShiftStrategy implements RecommendationEngine
{
    private const METHODOLOGY_VERSION = 'provisional-window-shift-v1';

    /** €1 — below this, not worth surfacing as a recommendation. */
    private const MIN_SAVINGS_CENTS = 100;

    /** Require at least 5% cheaper to bother proposing a shift. */
    private const MIN_IMPROVEMENT_FRACTION = 0.05;

    public function methodologyVersion(): string
    {
        return self::METHODOLOGY_VERSION;
    }

    public function isProvisional(): bool
    {
        return true;
    }

    public function generate(RecommendationEngineInput $input): RecommendationEngineResult
    {
        /** @var array<string, array<int, \App\Domain\Optimization\DTOs\PricePoint>> $pricesByDay */
        $pricesByDay = [];
        foreach ($input->prices as $price) {
            $pricesByDay[$this->dayKey($price->hourStart)][] = $price;
        }
        foreach ($pricesByDay as $key => $list) {
            usort($list, fn ($a, $b) => $this->hourOf($a->hourStart) <=> $this->hourOf($b->hourStart));
            $pricesByDay[$key] = $list;
        }

        $recommendations = [];

        foreach ($input->blocks as $block) {
            if ($block->estimatedKwh === null || $block->estimatedKwh <= 0) {
                continue;
            }

            $durationMs = $block->startAt->diffInMilliseconds($block->endAt);
            $durationHours = max(1, (int) round($durationMs / (60 * 60 * 1000)));
            $dayPrices = $pricesByDay[$this->dayKey($block->startAt)] ?? null;
            if ($dayPrices === null || count($dayPrices) < $durationHours) {
                continue;
            }

            $startHour = $this->hourOf($block->startAt);
            $currentWindow = array_values(array_filter(
                $dayPrices,
                function ($p) use ($startHour, $durationHours) {
                    $h = $this->hourOf($p->hourStart);

                    return $h >= $startHour && $h < $startHour + $durationHours;
                },
            ));
            if (count($currentWindow) === 0) {
                continue;
            }
            $currentAvgPrice = array_sum(array_map(fn ($p) => $p->eurPerMwh, $currentWindow)) / count($currentWindow);

            $bestStartIdx = -1;
            $bestAvgPrice = $currentAvgPrice;
            $dayPricesCount = count($dayPrices);
            for ($i = 0; $i + $durationHours <= $dayPricesCount; $i++) {
                $window = array_slice($dayPrices, $i, $durationHours);
                $avg = array_sum(array_map(fn ($p) => $p->eurPerMwh, $window)) / count($window);
                if ($avg < $bestAvgPrice) {
                    $bestAvgPrice = $avg;
                    $bestStartIdx = $i;
                }
            }

            if ($bestStartIdx === -1) {
                continue;
            }
            if ($bestAvgPrice > $currentAvgPrice * (1 - self::MIN_IMPROVEMENT_FRACTION)) {
                continue;
            }

            $projectedSavingsCents = (int) round(($currentAvgPrice - $bestAvgPrice) * $block->estimatedKwh / 1000 * 100);
            if ($projectedSavingsCents < self::MIN_SAVINGS_CENTS) {
                continue;
            }

            $bestPrice = $dayPrices[$bestStartIdx];
            $newStartHour = $this->hourOf($bestPrice->hourStart);
            $proposedStartAt = $bestPrice->hourStart->copy();
            $proposedEndAt = $proposedStartAt->copy()->addHours($durationHours);

            $shiftHours = abs($newStartHour - $startHour);
            $deliveryRisk = $shiftHours <= 3
                ? DeliveryRisk::Low
                : ($shiftHours <= 8 ? DeliveryRisk::Medium : DeliveryRisk::High);

            $recommendations[] = new RecommendationOutput(
                scheduleBlockId: $block->id,
                proposedStartAt: $proposedStartAt,
                proposedEndAt: $proposedEndAt,
                projectedSavingsCents: $projectedSavingsCents,
                deliveryRisk: $deliveryRisk,
                rationale: sprintf(
                    'Shift %s from %02d:00 to %02d:00 — ~€%s/MWh cheaper.',
                    $block->assetLabel,
                    $startHour,
                    $newStartHour,
                    number_format($currentAvgPrice - $bestAvgPrice, 1),
                ),
            );
        }

        return new RecommendationEngineResult(
            methodologyVersion: self::METHODOLOGY_VERSION,
            isProvisional: true,
            recommendations: $recommendations,
        );
    }

    private function dayKey(Carbon $date): string
    {
        return $date->copy()->utc()->format('Y-m-d');
    }

    private function hourOf(Carbon $date): int
    {
        return (int) $date->copy()->utc()->format('G');
    }
}
