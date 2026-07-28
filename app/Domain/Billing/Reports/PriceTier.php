<?php

namespace App\Domain\Billing\Reports;

/** Mirrors the Next.js app's classifyPriceTier() (src/components/ui/badge.tsx) — shared thresholds for the report's heatmap/schedule-bar coloring. */
class PriceTier
{
    public static function classify(float $eurPerMwh, float $cheapBelow = 40.0, float $expensiveAbove = 58.0): string
    {
        if ($eurPerMwh < $cheapBelow) {
            return 'cheap';
        }
        if ($eurPerMwh > $expensiveAbove) {
            return 'expensive';
        }

        return 'mid';
    }
}
