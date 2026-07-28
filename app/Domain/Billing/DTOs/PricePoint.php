<?php

namespace App\Domain\Billing\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors the Next.js app's `PricePoint` interface (src/lib/savings/types.ts, src/lib/verification/types.ts). */
final class PricePoint
{
    public function __construct(
        public readonly Carbon $hourStart,
        public readonly float $eurPerMwh,
    ) {}
}
