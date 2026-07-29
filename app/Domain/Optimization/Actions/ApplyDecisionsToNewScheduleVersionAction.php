<?php

namespace App\Domain\Optimization\Actions;

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Optimization\DTOs\ApplyDecisionsResult;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Scheduling\Enums\ScheduleStatus;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's applyDecisionsToNewScheduleVersion() Server
 * Action exactly (src/app/(portal)/dashboard/schedule/actions.ts). Rolls
 * up every not-yet-applied ACCEPTED/PARTIAL recommendation decision into a
 * new, immutable schedule version (Phase 4 §4: "versioned, not
 * overwritten").
 *
 * "Applied" is tracked implicitly rather than with a separate flag: once a
 * block is copied forward into version N+1, the decisions tied to the old
 * (now SUPERSEDED) version's blocks can never be found again by this
 * query, because it only ever looks at decisions on the CURRENT latest
 * schedule's blocks. There is structurally no way to double-apply a
 * decision.
 *
 * Does not write back to the customer's own ERP (Phase 2 §5.2 — "manually
 * re-entered in ERP in v1... a natural v2, not a v1 requirement"). This is
 * Hetkin's own internal record of what was decided.
 */
class ApplyDecisionsToNewScheduleVersionAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
        private readonly PostHogService $postHog,
    ) {}

    /**
     * @throws OptimizationActionException
     */
    public function execute(Organization $organization, User $user): ApplyDecisionsResult
    {
        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            /** @var ProductionSchedule|null $currentSchedule */
            $currentSchedule = ProductionSchedule::query()
                ->where('organization_id', $organization->id)
                ->orderByDesc('version')
                ->with('blocks')
                ->first();

            if (! $currentSchedule || $currentSchedule->blocks->isEmpty()) {
                throw new OptimizationActionException('No schedule to apply changes to yet.');
            }

            $decisions = RecommendationDecision::query()
                ->whereIn('decision', [RecommendationDecisionType::Accepted, RecommendationDecisionType::Partial])
                ->whereHas('recommendation.scheduleBlock', fn ($query) => $query->where('schedule_id', $currentSchedule->id))
                ->with('recommendation')
                ->get();

            if ($decisions->isEmpty()) {
                throw new OptimizationActionException('No accepted or partially-adopted recommendations to apply yet.');
            }

            /** @var array<string, array{start_at: Carbon, end_at: Carbon}> $overrides */
            $overrides = [];
            foreach ($decisions as $decision) {
                $recommendation = $decision->recommendation;
                if (! $recommendation->schedule_block_id) {
                    continue;
                }
                if ($decision->decision === RecommendationDecisionType::Accepted) {
                    $overrides[$recommendation->schedule_block_id] = [
                        'start_at' => $recommendation->proposed_start_at,
                        'end_at' => $recommendation->proposed_end_at,
                    ];
                } elseif ($decision->decision === RecommendationDecisionType::Partial
                    && $decision->adjusted_start_at
                    && $decision->adjusted_end_at) {
                    $overrides[$recommendation->schedule_block_id] = [
                        'start_at' => $decision->adjusted_start_at,
                        'end_at' => $decision->adjusted_end_at,
                    ];
                }
            }

            if (count($overrides) === 0) {
                throw new OptimizationActionException('No applicable schedule changes found among recent decisions.');
            }

            $newVersion = $currentSchedule->version + 1;

            $result = DB::transaction(function () use ($organization, $user, $currentSchedule, $overrides, $newVersion) {
                $created = ProductionSchedule::create([
                    'organization_id' => $organization->id,
                    'version' => $newVersion,
                    'status' => ScheduleStatus::Active,
                    'submitted_by_id' => $user->id,
                ]);

                foreach ($currentSchedule->blocks as $block) {
                    $override = $overrides[$block->id] ?? null;

                    ScheduleBlock::create([
                        'schedule_id' => $created->id,
                        'site_id' => $block->site_id,
                        'asset_label' => $block->asset_label,
                        'start_at' => $override['start_at'] ?? $block->start_at,
                        'end_at' => $override['end_at'] ?? $block->end_at,
                        'description' => $block->description,
                        'estimated_kwh' => $block->estimated_kwh,
                    ]);
                }

                $currentSchedule->update(['status' => ScheduleStatus::Superseded]);

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'schedule.new_version_applied',
                    entityType: 'ProductionSchedule',
                    entityId: $created->id,
                    organizationId: $organization->id,
                    metadata: [
                        'previousVersion' => $currentSchedule->version,
                        'newVersion' => $newVersion,
                        'blocksChanged' => count($overrides),
                        'totalBlocks' => $currentSchedule->blocks->count(),
                    ],
                ));

                return new ApplyDecisionsResult(
                    schedule: $created->refresh(),
                    newVersion: $newVersion,
                    blocksChanged: count($overrides),
                );
            });

            $this->postHog->capture('schedule_version_applied', $user->id, [
                'organizationId' => $organization->id,
                'newVersion' => $result->newVersion,
                'blocksChanged' => $result->blocksChanged,
            ]);

            return $result;
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
