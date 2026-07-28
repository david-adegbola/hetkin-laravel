<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Contracts\SavingsCalculationService;
use App\Domain\Billing\DTOs\MeterReadingPoint as CalcMeterReadingPoint;
use App\Domain\Billing\DTOs\PricePoint as CalcPricePoint;
use App\Domain\Billing\DTOs\SavingsCalculationInput;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Enums\SavingsAssessmentStatus;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Exceptions\InsufficientDataException;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's generateSavingsAssessment() Server Action exactly
 * (src/app/(portal)/dashboard/reports/actions.ts). Unconditionally creates
 * a fresh DRAFT Baseline every time it runs, even if a LOCKED baseline
 * already exists for the org — this is v2's actual behavior, not an
 * oversight; see MIGRATION_PLAN.md's Stage 7 ADR for why it's ported
 * as-is rather than "fixed" to auto-supersede.
 *
 * Deliberately omits, matching v2 features this port hasn't built yet:
 *   - createAlert()'s REPORT_READY alert — the Alert domain doesn't exist
 *     yet (Stage 10; see MIGRATION_PLAN.md ADR-33).
 *   - PostHog `savings_assessment_generated` event (Stage 11).
 *   - Onboarding-status cache invalidation — same reasoning as
 *     UploadMeterReadingsAction/AddScheduleBlockAction.
 * Neither is silently dropped; both are noted here so a future stage
 * knows exactly what to add back once their domains exist.
 */
class GenerateSavingsAssessmentAction
{
    public function __construct(
        private readonly SavingsCalculationService $service,
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @throws BillingActionException
     */
    public function execute(Organization $organization, User $user): SavingsAssessment
    {
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            $readings = MeterReading::query()
                ->whereHas('meteringPoint.site', fn ($q) => $q->where('organization_id', $organization->id))
                ->orderBy('hour_start')
                ->get();

            if ($readings->isEmpty()) {
                throw new BillingActionException('No meter data uploaded yet — upload data first.');
            }

            $firstHour = $readings->first()->hour_start;
            $lastHour = $readings->last()->hour_start;

            $prices = ElectricityPrice::query()
                ->where('bidding_zone', BiddingZone::FI)
                ->where('hour_start', '>=', $firstHour)
                ->where('hour_start', '<=', $lastHour)
                ->get();

            if ($prices->isEmpty()) {
                throw new BillingActionException('No electricity price data covers your meter data\'s date range yet.');
            }

            try {
                $result = $this->service->calculate(new SavingsCalculationInput(
                    organizationId: $organization->id,
                    readings: $readings->map(fn (MeterReading $r) => new CalcMeterReadingPoint(
                        $r->hour_start,
                        (float) $r->consumption_kwh,
                    ))->all(),
                    prices: $prices->map(fn (ElectricityPrice $p) => new CalcPricePoint(
                        $p->hour_start,
                        (float) $p->eur_per_mwh,
                    ))->all(),
                ));
            } catch (InsufficientDataException $e) {
                throw new BillingActionException($e->getMessage(), previous: $e);
            }

            $revenueShareFraction = $organization->revenue_share_percent !== null
                ? ((float) $organization->revenue_share_percent) / 100
                : 0.0;
            $hetkinShareCents = (int) round($result->estimatedAnnualSavingsCents * $revenueShareFraction);
            $netCustomerSavingsCents = $result->estimatedAnnualSavingsCents - $hetkinShareCents;

            return DB::transaction(function () use (
                $organization, $user, $result, $hetkinShareCents,
                $netCustomerSavingsCents, $firstHour, $lastHour,
            ) {
                $baseline = Baseline::create([
                    'organization_id' => $organization->id,
                    'methodology_version' => $result->methodologyVersion,
                    'status' => BaselineStatus::Draft,
                    'assumptions' => $result->assumptions,
                    'baseline_annual_cost_cents' => $result->currentAnnualCostCents,
                ]);

                $assessment = SavingsAssessment::create([
                    'organization_id' => $organization->id,
                    'baseline_id' => $baseline->id,
                    'status' => SavingsAssessmentStatus::Draft,
                    'current_annual_cost_cents' => $result->currentAnnualCostCents,
                    'optimized_annual_cost_cents' => $result->optimizedAnnualCostCents,
                    'estimated_annual_savings_cents' => $result->estimatedAnnualSavingsCents,
                    'hetkin_share_cents' => $hetkinShareCents,
                    'net_customer_savings_cents' => $netCustomerSavingsCents,
                    'is_provisional' => $result->isProvisional,
                    'sample_day_count' => $result->sampleDayCount,
                    'period_start' => $firstHour,
                    'period_end' => $lastHour,
                ]);

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'savings_assessment.generated',
                    entityType: 'SavingsAssessment',
                    entityId: $assessment->id,
                    organizationId: $organization->id,
                    metadata: [
                        'baselineId' => $baseline->id,
                        'methodologyVersion' => $result->methodologyVersion,
                        'isProvisional' => $result->isProvisional,
                        'sampleDayCount' => $result->sampleDayCount,
                    ],
                ));

                return $assessment->refresh();
            });
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
