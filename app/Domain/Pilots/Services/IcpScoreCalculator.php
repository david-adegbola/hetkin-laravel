<?php

namespace App\Domain\Pilots\Services;

use App\Domain\Organizations\Enums\ProcessType;

/**
 * Line-for-line port of the Next.js app's `computeIcpScore()`
 * (src/lib/pilot.ts). Deterministic, 0-100, never null at compute time. A
 * triage signal for the human reviewer, not a gate — a low score does not
 * auto-reject anything.
 */
class IcpScoreCalculator
{
    /** Process types Phase 1 §2 identified as good schedule-flexibility fits. */
    private const FLEXIBLE_PROCESS_TYPES = [
        ProcessType::ColdStorage,
        ProcessType::MetalTreatment,
        ProcessType::PlasticsMolding,
        ProcessType::WoodProcessing,
        ProcessType::WaterTreatment,
        ProcessType::FoodProcessing,
        ProcessType::ChemicalBatch,
    ];

    public static function compute(ProcessType $processType, int $estimatedAnnualSpendEur): int
    {
        $processScore = in_array($processType, self::FLEXIBLE_PROCESS_TYPES, true) ? 60 : 20;

        $spendScore = match (true) {
            $estimatedAnnualSpendEur >= 150_000 => 40,
            $estimatedAnnualSpendEur >= 50_000 => 30,
            $estimatedAnnualSpendEur >= 10_000 => 20,
            default => 5,
        };

        return $processScore + $spendScore;
    }
}
