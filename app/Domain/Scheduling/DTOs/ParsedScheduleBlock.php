<?php

namespace App\Domain\Scheduling\DTOs;

use App\Domain\Scheduling\Enums\LabourClassification;
use Illuminate\Support\Carbon;

final class ParsedScheduleBlock
{
    public function __construct(
        public readonly string $assetLabel,
        public readonly Carbon $startAt,
        public readonly Carbon $endAt,
        public readonly ?float $estimatedKwh,
        public readonly LabourClassification $labourClassification,
        public readonly ?string $description,
        public readonly string $siteName,
    ) {}
}
