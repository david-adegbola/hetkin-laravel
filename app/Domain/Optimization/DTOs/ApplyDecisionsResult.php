<?php

namespace App\Domain\Optimization\DTOs;

use App\Domain\Scheduling\Models\ProductionSchedule;

final class ApplyDecisionsResult
{
    public function __construct(
        public readonly ProductionSchedule $schedule,
        public readonly int $newVersion,
        public readonly int $blocksChanged,
    ) {}
}
