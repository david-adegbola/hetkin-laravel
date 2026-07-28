<?php

namespace App\Domain\Metering\DTOs;

use App\Domain\Metering\Models\MeteringPoint;

final class UploadMeterReadingsResult
{
    public function __construct(
        public readonly int $rowsWritten,
        public readonly MeteringPoint $meteringPoint,
    ) {}
}
