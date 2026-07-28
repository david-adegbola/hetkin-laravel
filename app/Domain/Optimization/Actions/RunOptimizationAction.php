<?php

namespace App\Domain\Optimization\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Optimization\Contracts\RecommendationEngine;
use App\Domain\Optimization\DTOs\PricePoint as EnginePricePoint;
use App\Domain\Optimization\DTOs\RecommendationEngineInput;
use App\Domain\Optimization\DTOs\ScheduleBlockInput as EngineScheduleBlockInput;
use App\Domain\Optimization\Enums\OptimizationStatus;
use App\Domain\Optimization\Enums\OptimizationTrigger;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's runOptimization() Server Action exactly
 * (src/app/(portal)/dashboard/schedule/actions.ts). Deliberately omits:
 *   - createAlert()'s NEW_OPPORTUNITY alert — the Alert domain doesn't
 *     exist yet (Stage 10); see MIGRATION_PLAN.md ADR-33.
 *   - onboarding-status cache invalidation — same reasoning as
 *     UploadMeterReadingsAction/AddScheduleBlockAction.
 * Neither is silently dropped; both are noted here so a future stage
 * knows exactly what to add back once their domains exist.
 */
class RunOptimizationAction
{
    public function __construct(
        private readonly RecommendationEngine $engine,
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @throws OptimizationActionException
     */
    public function execute(Organization $organization, User $user): OptimizationRun
    {
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            /** @var ProductionSchedule|null $schedule */
            $schedule = ProductionSchedule::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('version')
                ->with('blocks')
                ->first();

            if (! $schedule || $schedule->blocks->isEmpty()) {
                throw new OptimizationActionException('Add at least one schedule block first.');
            }

            $blocksWithKwh = $schedule->blocks->filter(fn ($block) => $block->estimated_kwh !== null);
            if ($blocksWithKwh->isEmpty()) {
                throw new OptimizationActionException(
                    'No schedule blocks have an estimated energy use yet — add that when creating a block.',
                );
            }

            // Matches v2 exactly: the day range covers every block on the
            // schedule, not just the ones with an estimated kWh.
            $days = $schedule->blocks
                ->map(fn ($block) => $block->start_at->copy()->utc()->format('Y-m-d'))
                ->unique();
            $dayStart = Carbon::parse($days->min(), 'UTC')->startOfDay();
            $dayEnd = Carbon::parse($days->max(), 'UTC')->startOfDay()->addDay();

            $prices = ElectricityPrice::query()
                ->where('bidding_zone', BiddingZone::FI)
                ->where('hour_start', '>=', $dayStart)
                ->where('hour_start', '<', $dayEnd)
                ->get();

            if ($prices->isEmpty()) {
                throw new OptimizationActionException('No price data covers your schedule\'s dates yet.');
            }

            $result = $this->engine->generate(new RecommendationEngineInput(
                organizationId: $organization->id,
                scheduleId: $schedule->id,
                blocks: $blocksWithKwh->map(fn ($block) => new EngineScheduleBlockInput(
                    id: $block->id,
                    assetLabel: $block->asset_label,
                    startAt: $block->start_at,
                    endAt: $block->end_at,
                    estimatedKwh: $block->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                ))->values()->all(),
                prices: $prices->map(fn (ElectricityPrice $price) => new EnginePricePoint(
                    hourStart: $price->hour_start,
                    eurPerMwh: (float) $price->eur_per_mwh,
                ))->all(),
            ));

            return DB::transaction(function () use ($organization, $user, $schedule, $result) {
                $run = OptimizationRun::create([
                    'organization_id' => $organization->id,
                    'schedule_id' => $schedule->id,
                    'trigger' => OptimizationTrigger::Manual,
                    'status' => OptimizationStatus::Completed,
                    'methodology_version' => $result->methodologyVersion,
                    'completed_at' => now(),
                ]);

                foreach ($result->recommendations as $recommendation) {
                    Recommendation::create([
                        'optimization_run_id' => $run->id,
                        'schedule_block_id' => $recommendation->scheduleBlockId,
                        'proposed_start_at' => $recommendation->proposedStartAt,
                        'proposed_end_at' => $recommendation->proposedEndAt,
                        'projected_savings_cents' => $recommendation->projectedSavingsCents,
                        'delivery_risk' => $recommendation->deliveryRisk,
                        'rationale' => $recommendation->rationale,
                    ]);
                }

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'optimization_run.completed',
                    entityType: 'OptimizationRun',
                    entityId: $run->id,
                    organizationId: $organization->id,
                    metadata: [
                        'methodologyVersion' => $result->methodologyVersion,
                        'isProvisional' => $result->isProvisional,
                        'recommendationCount' => count($result->recommendations),
                    ],
                ));

                return $run->refresh();
            });
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
