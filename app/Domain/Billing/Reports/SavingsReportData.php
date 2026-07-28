<?php

namespace App\Domain\Billing\Reports;

use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Support\Collection;

/** Mirrors the shape of the Next.js app's `SavingsReportData` (src/lib/reports/savings-report.ts). */
final class SavingsReportData
{
    /**
     * @param  Collection<int, MeteringPoint>  $meteringPoints
     * @param  Collection<int, MeterReading>  $readings
     * @param  Collection<int, ElectricityPrice>  $prices
     * @param  Collection<int, ScheduleBlock>  $scheduleBlocks
     * @param  Collection<int, Recommendation>  $recommendations
     */
    public function __construct(
        public readonly SavingsAssessment $assessment,
        public readonly Organization $organization,
        public readonly Baseline $baseline,
        public readonly Collection $meteringPoints,
        public readonly Collection $readings,
        public readonly Collection $prices,
        public readonly Collection $scheduleBlocks,
        public readonly Collection $recommendations,
        public readonly int $versionNumber,
    ) {}
}
