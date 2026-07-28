<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Contracts\VerificationCalculationService;
use App\Domain\Billing\DTOs\LockedBaselineSummary;
use App\Domain\Billing\DTOs\MeterReadingPoint;
use App\Domain\Billing\DTOs\PricePoint;
use App\Domain\Billing\DTOs\VerificationCalculationInput;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Exceptions\InsufficientVerificationDataException;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Models\ElectricityPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Ports the Next.js app's runVerificationJob() exactly
 * (src/lib/jobs/run-verification.ts) — "shadow mode": this only ever
 * produces records with review_status SHADOW_PENDING_REVIEW (the model
 * default); nothing here can move a record to LIVE_APPROVED, only
 * App\Domain\Billing\Actions\ApproveLiveAction can, and only after
 * ConfirmShadowReviewAction. A plain callable class, extracted the same
 * way v2 extracted this from its cron route handler — so the exact same
 * logic runs whether triggered by a future Stage 10 scheduled command or
 * an admin's manual "Run now" (Stage 9's /admin/jobs). Neither trigger is
 * wired up yet; this Action exists and is fully tested so both can call it
 * directly once built.
 *
 * Genuinely cross-tenant (iterates every organization with a locked
 * baseline in one process) — bypasses OrganizationScope explicitly on
 * every scoped read/write rather than binding CurrentOrganization, same
 * reasoning as LockBaselineAction.
 *
 * One asymmetry preserved deliberately from v2: re-running for a period
 * that already has a VerificationRecord refreshes the computed figures
 * but leaves `baseline_id` and `review_status`/`reviewed_by`/`reviewed_at`
 * untouched — an admin's shadow/live decision (or which baseline a record
 * was originally computed against) is never silently changed by a re-run.
 */
class RunVerificationAction
{
    public function __construct(
        private readonly VerificationCalculationService $service,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{periodStart: string, periodEnd: string, organizationsConsidered: int, results: array<int, array{organizationId: string, status: string, detail?: string}>}
     */
    public function execute(?Carbon $periodStartOverride = null, ?Carbon $periodEndOverride = null): array
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($periodStartOverride, $periodEndOverride);

        if ($periodEnd->lessThanOrEqualTo($periodStart)) {
            throw new InvalidArgumentException('periodEnd must be after periodStart');
        }

        $organizationIds = Baseline::withoutGlobalScope(OrganizationScope::class)
            ->where('status', BaselineStatus::Locked)
            ->distinct()
            ->pluck('organization_id');

        $organizations = Organization::query()->whereIn('id', $organizationIds)->get();

        $results = [];

        foreach ($organizations as $organization) {
            try {
                /** @var Baseline|null $baseline */
                $baseline = Baseline::withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id)
                    ->where('status', BaselineStatus::Locked)
                    ->orderByDesc('locked_at')
                    ->first();

                if (! $baseline) {
                    $results[] = ['organizationId' => $organization->id, 'status' => 'skipped', 'detail' => 'No locked baseline'];

                    continue;
                }

                /** @var SavingsAssessment|null $latestAssessment */
                $latestAssessment = SavingsAssessment::withoutGlobalScope(OrganizationScope::class)
                    ->where('baseline_id', $baseline->id)
                    ->orderByDesc('generated_at')
                    ->first();

                $readings = MeterReading::query()
                    ->whereHas('meteringPoint.site', fn ($q) => $q
                        ->withoutGlobalScope(OrganizationScope::class)
                        ->where('organization_id', $organization->id))
                    ->where('hour_start', '>=', $periodStart)
                    ->where('hour_start', '<', $periodEnd)
                    ->get();

                $prices = ElectricityPrice::query()
                    ->where('bidding_zone', BiddingZone::FI)
                    ->where('hour_start', '>=', $periodStart)
                    ->where('hour_start', '<', $periodEnd)
                    ->get();

                try {
                    $result = $this->service->calculate(new VerificationCalculationInput(
                        organizationId: $organization->id,
                        baseline: new LockedBaselineSummary(
                            id: $baseline->id,
                            methodologyVersion: $baseline->methodology_version,
                            baselineAnnualCostCents: $baseline->baseline_annual_cost_cents,
                            latestOptimizedAnnualCostCents: $latestAssessment?->optimized_annual_cost_cents,
                        ),
                        periodStart: $periodStart,
                        periodEnd: $periodEnd,
                        readings: $readings->map(fn (MeterReading $r) => new MeterReadingPoint(
                            $r->hour_start,
                            (float) $r->consumption_kwh,
                        ))->all(),
                        prices: $prices->map(fn (ElectricityPrice $p) => new PricePoint(
                            $p->hour_start,
                            (float) $p->eur_per_mwh,
                        ))->all(),
                    ));
                } catch (InsufficientVerificationDataException $e) {
                    $results[] = ['organizationId' => $organization->id, 'status' => 'skipped', 'detail' => $e->getMessage()];

                    continue;
                }

                DB::transaction(function () use ($organization, $baseline, $periodStart, $periodEnd, $result) {
                    /** @var VerificationRecord|null $existing */
                    $existing = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
                        ->where('organization_id', $organization->id)
                        ->where('period_start', $periodStart)
                        ->where('period_end', $periodEnd)
                        ->first();

                    $figures = [
                        'baseline_cost_cents' => $result->baselineCostCents,
                        'actual_cost_cents' => $result->actualCostCents,
                        'verified_savings_cents' => $result->verifiedSavingsCents,
                        'variance_percent' => $result->variancePercent,
                        'variance_flagged' => $result->varianceFlagged,
                        'methodology_version' => $result->methodologyVersion,
                        'assumptions' => $result->assumptions,
                    ];

                    if ($existing) {
                        $existing->update($figures);
                        $record = $existing;
                    } else {
                        $record = VerificationRecord::create([
                            'organization_id' => $organization->id,
                            'baseline_id' => $baseline->id,
                            'period_start' => $periodStart,
                            'period_end' => $periodEnd,
                            ...$figures,
                        ]);
                    }

                    $this->auditLogService->record(new AuditEvent(
                        actorType: AuditActorType::System,
                        actorId: 'run-verification-job',
                        action: 'verification_record.computed',
                        entityType: 'VerificationRecord',
                        entityId: $record->id,
                        organizationId: $organization->id,
                        metadata: [
                            'methodologyVersion' => $result->methodologyVersion,
                            'isProvisional' => $result->isProvisional,
                            'varianceFlagged' => $result->varianceFlagged,
                            'periodStart' => $periodStart->toIso8601String(),
                            'periodEnd' => $periodEnd->toIso8601String(),
                        ],
                    ));
                });

                // Deliberately omitted, matching v2 features this port
                // hasn't built yet: the VARIANCE_FLAG Alert (Stage 10) and
                // the `verification_computed` PostHog event (Stage 11).
                $results[] = ['organizationId' => $organization->id, 'status' => 'computed'];
            } catch (Throwable $e) {
                $results[] = ['organizationId' => $organization->id, 'status' => 'error', 'detail' => $e->getMessage()];
            }
        }

        return [
            'periodStart' => $periodStart->toIso8601String(),
            'periodEnd' => $periodEnd->toIso8601String(),
            'organizationsConsidered' => $organizations->count(),
            'results' => $results,
        ];
    }

    /**
     * Defaults to the previous full UTC calendar month; both params must
     * be supplied together to override.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(?Carbon $periodStartOverride, ?Carbon $periodEndOverride): array
    {
        if ($periodStartOverride && $periodEndOverride) {
            return [$periodStartOverride, $periodEndOverride];
        }

        $thisMonthStart = Carbon::now('UTC')->startOfMonth()->startOfDay();
        $previousMonthStart = $thisMonthStart->copy()->subMonthNoOverflow();

        return [$previousMonthStart, $thisMonthStart];
    }
}
