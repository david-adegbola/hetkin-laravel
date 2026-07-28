<?php

namespace App\Domain\Optimization\DTOs;

use Illuminate\Support\Carbon;

/** Mirrors src/lib/optimization/types.ts's `ScheduleBlockInput` interface. */
final class ScheduleBlockInput
{
    public function __construct(
        public readonly string $id,
        public readonly string $assetLabel,
        public readonly Carbon $startAt,
        public readonly Carbon $endAt,
        /** Null blocks are skipped — no consumption figure, no basis for a savings estimate. */
        public readonly ?float $estimatedKwh,
    ) {}
}
