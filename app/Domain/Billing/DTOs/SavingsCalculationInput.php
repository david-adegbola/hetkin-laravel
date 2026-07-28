<?php

namespace App\Domain\Billing\DTOs;

/** Mirrors the Next.js app's `SavingsCalculationInput` interface (src/lib/savings/types.ts). */
final class SavingsCalculationInput
{
    /**
     * @param  array<int, MeterReadingPoint>  $readings
     * @param  array<int, PricePoint>  $prices
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly array $readings,
        public readonly array $prices,
    ) {}
}
