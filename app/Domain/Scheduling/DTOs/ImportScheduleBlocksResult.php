<?php

namespace App\Domain\Scheduling\DTOs;

use App\Domain\Scheduling\Models\ProductionSchedule;

final class ImportScheduleBlocksResult
{
    public function __construct(
        public readonly ProductionSchedule $schedule,
        public readonly int $rowsWritten,
    ) {}
}
