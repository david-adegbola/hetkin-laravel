<?php

namespace App\Domain\Billing\DTOs;

/** Mirrors the Next.js app's `SavingsCalculationResult` interface (src/lib/savings/types.ts). */
final class SavingsCalculationResult
{
    /**
     * @param  array<string, mixed>  $assumptions
     */
    public function __construct(
        public readonly string $methodologyVersion,
        public readonly bool $isProvisional,
        public readonly int $currentAnnualCostCents,
        public readonly int $optimizedAnnualCostCents,
        public readonly int $estimatedAnnualSavingsCents,
        public readonly int $sampleDayCount,
        public readonly array $assumptions,
    ) {}
}
