<?php

namespace App\Domain\Metering\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors src/lib/meter-csv.ts's `ParsedMeterRow` interface. */
final class ParsedMeterRow
{
    public function __construct(
        public readonly Carbon $hourStart,
        public readonly float $consumptionKwh,
    ) {}
}
