<?php

namespace App\Domain\Optimization\DTOs;

/** Mirrors src/lib/optimization/types.ts's `RecommendationEngineInput` interface. */
final class RecommendationEngineInput
{
    /**
     * @param  array<int, ScheduleBlockInput>  $blocks
     * @param  array<int, PricePoint>  $prices  Should cover at least the calendar days the blocks fall on.
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $scheduleId,
        public readonly array $blocks,
        public readonly array $prices,
    ) {}
}
