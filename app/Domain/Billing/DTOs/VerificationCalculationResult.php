<?php

namespace App\Domain\Billing\DTOs;

/** Mirrors the Next.js app's `VerificationCalculationResult` interface (src/lib/verification/types.ts). */
final class VerificationCalculationResult
{
    /**
     * @param  array<string, mixed>  $assumptions
     */
    public function __construct(
        public readonly string $methodologyVersion,
        public readonly bool $isProvisional,
        public readonly int $baselineCostCents,
        public readonly int $actualCostCents,
        public readonly int $verifiedSavingsCents,
        public readonly float $variancePercent,
        public readonly bool $varianceFlagged,
        public readonly array $assumptions,
    ) {}
}
