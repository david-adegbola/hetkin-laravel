<?php

namespace App\Domain\Optimization\Contracts;

use App\Domain\Optimization\DTOs\RecommendationEngineInput;
use App\Domain\Optimization\DTOs\RecommendationEngineResult;

/**
 * Mirrors src/lib/optimization/types.ts's `RecommendationEngine` interface
 * — the recommendation-engine seam. Phase 5 M2 explicitly calls for "v1 as
 * a constrained heuristic, not a general solver," so the concrete
 * algorithm lives behind an interface from day one rather than being
 * hardcoded into the Action that calls it.
 *
 * The "single swap point" v2's src/lib/optimization/index.ts describes
 * (everything that needs recommendations calls one function rather than
 * importing a strategy class directly) is this interface bound to
 * App\Domain\Optimization\Strategies\ProvisionalWindowShiftStrategy in
 * AppServiceProvider — the idiomatic Laravel equivalent of a factory
 * function.
 */
interface RecommendationEngine
{
    public function methodologyVersion(): string;

    public function isProvisional(): bool;

    public function generate(RecommendationEngineInput $input): RecommendationEngineResult;
}
