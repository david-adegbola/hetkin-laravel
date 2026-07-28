<?php

namespace App\Domain\Pricing\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors the Next.js app's `PriceEntry` interface (src/lib/prices/provider.ts). */
final class PricePoint
{
    public function __construct(
        public readonly Carbon $hourStart,
        public readonly float $eurPerMwh,
    ) {}
}
