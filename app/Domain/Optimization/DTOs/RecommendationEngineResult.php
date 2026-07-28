<?php

namespace App\Domain\Optimization\DTOs;

/** Mirrors src/lib/optimization/types.ts's `RecommendationEngineResult` interface. */
final class RecommendationEngineResult
{
    /**
     * @param  array<int, RecommendationOutput>  $recommendations
     */
    public function __construct(
        public readonly string $methodologyVersion,
        public readonly bool $isProvisional,
        public readonly array $recommendations,
    ) {}
}
