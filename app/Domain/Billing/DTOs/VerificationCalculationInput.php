<?php

namespace App\Domain\Billing\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors the Next.js app's `VerificationCalculationInput` interface (src/lib/verification/types.ts). */
final class VerificationCalculationInput
{
    /**
     * @param  array<int, MeterReadingPoint>  $readings
     * @param  array<int, PricePoint>  $prices
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly LockedBaselineSummary $baseline,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
        public readonly array $readings,
        public readonly array $prices,
    ) {}
}
