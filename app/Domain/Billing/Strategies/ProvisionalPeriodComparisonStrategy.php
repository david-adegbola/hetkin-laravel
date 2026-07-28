<?php

namespace App\Domain\Billing\Strategies;

use App\Domain\Billing\Contracts\VerificationCalculationService;
use App\Domain\Billing\DTOs\VerificationCalculationInput;
use App\Domain\Billing\DTOs\VerificationCalculationResult;
use App\Domain\Billing\Exceptions\InsufficientVerificationDataException;
use Illuminate\Support\Carbon;

/**
 * Line-for-line port of the Next.js app's `ProvisionalPeriodComparisonStrategy`
 * (src/lib/verification/strategies/provisional-period-comparison-strategy.ts).
 *
 * The comparison it draws:
 *  - actualCostCents: real metered consumption × real prices for the
 *    period — ground truth, not modeled.
 *  - baselineCostCents: the LOCKED baseline's annual cost, scaled down to
 *    the period length — "what this period would have cost under the
 *    counterfactual the baseline represents."
 *  - verifiedSavingsCents = baselineCostCents − actualCostCents.
 *  - variancePercent compares actualCostCents against what the most recent
 *    SavingsAssessment for this baseline told the customer to *expect*
 *    (its optimizedAnnualCostCents, scaled to the period) — the sanity
 *    check shadow mode exists for: if reality drifts far from what the
 *    customer was shown, a human looks before this record can back an
 *    invoice. See App\Domain\Billing\Actions\ConfirmShadowReviewAction /
 *    ApproveLiveAction / DisputeRecordAction / ReopenDisputedAction.
 */
class ProvisionalPeriodComparisonStrategy implements VerificationCalculationService
{
    private const METHODOLOGY_VERSION = 'provisional-period-comparison-v1';

    private const VARIANCE_FLAG_THRESHOLD_PERCENT = 20;

    public function methodologyVersion(): string
    {
        return self::METHODOLOGY_VERSION;
    }

    public function isProvisional(): bool
    {
        return true;
    }

    /**
     * @throws InsufficientVerificationDataException
     */
    public function calculate(VerificationCalculationInput $input): VerificationCalculationResult
    {
        $priceMap = [];
        foreach ($input->prices as $price) {
            $priceMap[$this->timeKey($price->hourStart)] = $price->eurPerMwh;
        }

        $actualCostEur = 0.0;
        $matchedHours = 0;
        foreach ($input->readings as $reading) {
            $key = $this->timeKey($reading->hourStart);
            if (! array_key_exists($key, $priceMap)) {
                continue;
            }
            $actualCostEur += $reading->consumptionKwh * $priceMap[$key] / 1000;
            $matchedHours++;
        }

        if ($matchedHours < 24 * 5) {
            throw new InsufficientVerificationDataException(
                "Only {$matchedHours} hour(s) of matched meter + price data for this period — need at least 5 days' worth for even a provisional verification.",
            );
        }

        $periodDays = $input->periodStart->diffInSeconds($input->periodEnd) / (24 * 60 * 60);
        $periodFraction = $periodDays / 365;

        $actualCostCents = (int) round($actualCostEur * 100);
        $baselineCostCents = (int) round($input->baseline->baselineAnnualCostCents * $periodFraction);
        $verifiedSavingsCents = $baselineCostCents - $actualCostCents;

        $variancePercent = 0.0;
        $varianceFlagged = false;
        if ($input->baseline->latestOptimizedAnnualCostCents !== null) {
            $expectedCostCents = (int) round($input->baseline->latestOptimizedAnnualCostCents * $periodFraction);
            if ($expectedCostCents !== 0) {
                $variancePercent = ($actualCostCents - $expectedCostCents) / $expectedCostCents * 100;
                $varianceFlagged = abs($variancePercent) > self::VARIANCE_FLAG_THRESHOLD_PERCENT;
            }
        } else {
            // No SavingsAssessment to compare against — flag for review
            // rather than silently reporting a zero variance that looks clean.
            $varianceFlagged = true;
        }

        return new VerificationCalculationResult(
            methodologyVersion: self::METHODOLOGY_VERSION,
            isProvisional: true,
            baselineCostCents: $baselineCostCents,
            actualCostCents: $actualCostCents,
            verifiedSavingsCents: $verifiedSavingsCents,
            variancePercent: round($variancePercent, 2),
            varianceFlagged: $varianceFlagged,
            assumptions: [
                'periodDays' => $periodDays,
                'periodFraction' => $periodFraction,
                'matchedHours' => $matchedHours,
                'varianceFlagThresholdPercent' => self::VARIANCE_FLAG_THRESHOLD_PERCENT,
                'baselineMethodologyVersion' => $input->baseline->methodologyVersion,
                'note' => 'Provisional period-comparison verification — not a legally-reviewed M&V methodology. See hetkin-phase1-business-discovery.md §3.',
            ],
        );
    }

    private function timeKey(Carbon $d): int
    {
        return $d->copy()->utc()->timestamp;
    }
}
