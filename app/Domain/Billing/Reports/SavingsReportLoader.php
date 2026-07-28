<?php

namespace App\Domain\Billing\Reports;

use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Optimization\Enums\OptimizationStatus;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use Illuminate\Support\Collection;

/**
 * Ports the Next.js app's loadSavingsReportData() exactly
 * (src/lib/reports/savings-report.ts) — the only DB-touching half of the
 * report; App\Domain\Billing\Reports\SavingsReportViewModelBuilder is a
 * pure function of what this returns, so the web page and the PDF can
 * never drift out of sync.
 *
 * `productionProfile` is always "Not provided." in this port — v2 reads it
 * from `organization.pilotApplication.winCondition`, and the pilot-funnel
 * domain doesn't exist yet (Stage 8). Not a silent gap: the fallback
 * string is the same one v2 itself shows for an org with no pilot
 * application, so this port's current behavior is indistinguishable from
 * that same v2 state — only becomes wrong once Stage 8 exists and this
 * loader hasn't been updated to read it.
 *
 * Relies on the ambient App\Domain\Organizations\CurrentOrganization bound
 * by App\Http\Middleware\ResolveOrganizationContext for every portal
 * request — every query below is additionally filtered by an explicit
 * `organization_id`/relation match, matching v2's own belt-and-suspenders
 * `{ ..., organizationId }` where clauses even though its caller already
 * holds an org-scoped Prisma client.
 */
class SavingsReportLoader
{
    public function load(string $assessmentId, string $organizationId): ?SavingsReportData
    {
        /** @var SavingsAssessment|null $assessment */
        $assessment = SavingsAssessment::query()
            ->where('id', $assessmentId)
            ->where('organization_id', $organizationId)
            ->with(['baseline', 'organization'])
            ->first();

        if (! $assessment) {
            return null;
        }

        $periodStart = $assessment->period_start;
        $periodEnd = $assessment->period_end;

        $meteringPoints = MeteringPoint::query()
            ->whereHas('site', fn ($q) => $q->where('organization_id', $organizationId))
            ->with('site')
            ->get();

        $readings = new Collection;
        $prices = new Collection;
        if ($periodStart && $periodEnd) {
            $readings = MeterReading::query()
                ->whereHas('meteringPoint.site', fn ($q) => $q->where('organization_id', $organizationId))
                ->whereBetween('hour_start', [$periodStart, $periodEnd])
                ->orderBy('hour_start')
                ->get();

            $prices = ElectricityPrice::query()
                ->where('bidding_zone', BiddingZone::FI)
                ->whereBetween('hour_start', [$periodStart, $periodEnd])
                ->orderBy('hour_start')
                ->get();
        }

        // "Current" schedule for the schedule-optimization section: the
        // org's most recently created ProductionSchedule — not tied 1:1 to
        // this specific assessment's period (no such link exists in the
        // schema), a known simplification carried over from v2.
        /** @var ProductionSchedule|null $latestSchedule */
        $latestSchedule = ProductionSchedule::query()
            ->where('organization_id', $organizationId)
            ->orderByDesc('version')
            ->with(['blocks' => fn ($q) => $q->orderBy('start_at')])
            ->with(['optimizationRuns' => fn ($q) => $q->where('status', OptimizationStatus::Completed)
                ->orderByDesc('completed_at')
                ->limit(1)])
            ->first();

        $recommendations = new Collection;
        $latestRun = $latestSchedule?->optimizationRuns->first();
        if ($latestRun) {
            $recommendations = Recommendation::query()
                ->where('optimization_run_id', $latestRun->id)
                ->with('scheduleBlock')
                ->orderByDesc('projected_savings_cents')
                ->get();
        }

        // For the cover page's version number — how many assessments this
        // org has generated up to and including this one.
        $allAssessmentIds = SavingsAssessment::query()
            ->where('organization_id', $organizationId)
            ->orderBy('generated_at')
            ->pluck('id');
        $index = $allAssessmentIds->search($assessmentId);
        $versionNumber = $index === false ? 0 : $index + 1;

        return new SavingsReportData(
            assessment: $assessment,
            organization: $assessment->organization,
            baseline: $assessment->baseline,
            meteringPoints: $meteringPoints,
            readings: $readings,
            prices: $prices,
            scheduleBlocks: $latestSchedule->blocks ?? new Collection,
            recommendations: $recommendations,
            versionNumber: $versionNumber,
        );
    }
}
