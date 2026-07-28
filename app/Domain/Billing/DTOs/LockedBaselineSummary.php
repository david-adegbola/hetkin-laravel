<?php

namespace App\Domain\Billing\DTOs;

/** Mirrors the Next.js app's `LockedBaselineSummary` interface (src/lib/verification/types.ts). */
final class LockedBaselineSummary
{
    public function __construct(
        public readonly string $id,
        public readonly string $methodologyVersion,
        public readonly int $baselineAnnualCostCents,
        /**
         * Most recent SavingsAssessment's optimizedAnnualCostCents for this
         * baseline, if one exists — used as the "what we told the customer
         * to expect" reference point for variance.
         */
        public readonly ?int $latestOptimizedAnnualCostCents,
    ) {}
}
