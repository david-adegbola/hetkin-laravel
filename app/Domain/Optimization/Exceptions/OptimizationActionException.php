<?php

namespace App\Domain\Optimization\Exceptions;

use RuntimeException;

/**
 * Every message this is thrown with is shown directly to the user —
 * mirrors the `{ status: "error", message: string }` branch of v2's
 * RunOptimizationState/DecideRecommendationState/ApplyDecisionsState
 * union return types (src/app/(portal)/dashboard/schedule/actions.ts,
 * src/app/(portal)/dashboard/actions.ts). An exception is the more
 * idiomatic PHP shape for the same "business rule failed, tell the user
 * why" case a discriminated union return type expresses in TypeScript —
 * same reasoning as App\Domain\Metering\Exceptions\InvalidMeterCsvException.
 */
class OptimizationActionException extends RuntimeException {}
