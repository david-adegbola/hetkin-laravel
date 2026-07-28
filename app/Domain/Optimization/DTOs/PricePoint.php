<?php

namespace App\Domain\Optimization\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors src/lib/optimization/types.ts's `PricePoint` interface. */
final class PricePoint
{
    public function __construct(
        public readonly Carbon $hourStart,
        public readonly float $eurPerMwh,
    ) {}
}
