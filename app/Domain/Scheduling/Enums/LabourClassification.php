<?php

namespace App\Domain\Scheduling\Enums;

/**
 * A property of a ScheduleBlock, not a second load-type taxonomy — see
 * ScheduleBlock::isEligibleForOptimization()'s docblock. New product
 * scope (customer discovery, not a v2 port — v2 has no such concept, see
 * MIGRATION_PLAN.md's Labour-Aware Shiftable Load Classification ADR).
 */
enum LabourClassification: string
{
    /** Can already run without anyone changing when they work — eligible for customer-facing recommendations. */
    case Unmanned = 'UNMANNED';

    /** Shifting it means someone works a different time — never eligible for customer-facing recommendations. */
    case LabourBound = 'LABOUR_BOUND';

    public function label(): string
    {
        return match ($this) {
            self::Unmanned => 'Unmanned',
            self::LabourBound => 'Labour Bound',
        };
    }
}
