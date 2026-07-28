<?php

namespace App\Domain\Optimization\DTOs;

use App\Domain\Optimization\Enums\DeliveryRisk;
use Illuminate\Support\Carbon;

/** Mirrors src/lib/optimization/types.ts's `RecommendationOutput` interface. */
final class RecommendationOutput
{
    public function __construct(
        public readonly string $scheduleBlockId,
        public readonly Carbon $proposedStartAt,
        public readonly Carbon $proposedEndAt,
        public readonly int $projectedSavingsCents,
        public readonly DeliveryRisk $deliveryRisk,
        public readonly string $rationale,
    ) {}
}
