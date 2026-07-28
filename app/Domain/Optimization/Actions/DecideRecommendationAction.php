<?php

namespace App\Domain\Optimization\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's decideRecommendation() Server Action exactly
 * (src/app/(portal)/dashboard/actions.ts). Does not touch the actual
 * production schedule — the schedule only changes when
 * ApplyDecisionsToNewScheduleVersionAction runs, which rolls up every
 * not-yet-applied ACCEPTED/PARTIAL decision into a new schedule version.
 *
 * Three decision types, matching Phase 2 §1's design implication:
 * "recommendations must ... be easy to reject with a reason."
 *   - ACCEPTED: adopt exactly as proposed. No reason required.
 *   - REJECTED: reason is required — the scheduler-trust signal Phase 2
 *     §1 calls a "strategic asset," not an afterthought.
 *   - PARTIAL: adopting the recommendation, but to a self-chosen time
 *     rather than exactly what was proposed. Requires an adjusted start
 *     time + duration; reason is optional context.
 *
 * Omits src/lib/analytics/server.ts's captureServerEvent (Stage 11, not
 * built) — not silently dropped, see MIGRATION_PLAN.md ADR-33.
 */
class DecideRecommendationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    /**
     * @throws OptimizationActionException
     */
    public function execute(
        Organization $organization,
        User $user,
        string $recommendationId,
        RecommendationDecisionType $decision,
        ?string $reason,
        ?Carbon $adjustedStartAt,
        ?int $durationMinutes,
    ): RecommendationDecision {
        if ($decision === RecommendationDecisionType::Rejected && ! $reason) {
            throw new OptimizationActionException('A reason is required to reject a recommendation.');
        }

        $adjustedEndAt = null;
        if ($decision === RecommendationDecisionType::Partial) {
            if (! $adjustedStartAt) {
                throw new OptimizationActionException('Enter the time you\'re actually planning to move this to.');
            }
            if ($durationMinutes === null || $durationMinutes <= 0) {
                throw new OptimizationActionException(
                    'Could not determine the block\'s duration for the adjusted time.',
                );
            }
            $adjustedEndAt = $adjustedStartAt->copy()->addMinutes($durationMinutes);
        }

        // Bootstrap-shaped lookup, same reasoning as
        // OrganizationContextResolver: we're verifying the recommendation
        // belongs to this organization, not querying something already
        // known to be scoped to it — so `optimizationRun` is eager-loaded
        // with its own BelongsToOrganization scope explicitly bypassed.
        // Loading it WITH that scope active (i.e. scoped to $organization
        // before this check runs) would silently come back null for a
        // recommendation belonging to a DIFFERENT organization — not
        // because it doesn't exist, but because the scope correctly
        // filtered it out — which would crash the null-property check
        // below instead of reaching the intended "not found" branch.
        /** @var Recommendation|null $recommendation */
        $recommendation = Recommendation::query()
            ->with([
                'optimizationRun' => fn ($query) => $query->withoutGlobalScope(OrganizationScope::class),
                'decision',
            ])
            ->find($recommendationId);

        if (! $recommendation || $recommendation->optimizationRun->organization_id !== $organization->id) {
            throw new OptimizationActionException('Recommendation not found.');
        }
        if ($recommendation->decision) {
            throw new OptimizationActionException('This recommendation already has a decision recorded.');
        }

        $previousOrganization = $this->currentOrganization->get();
        $this->currentOrganization->set($organization);

        try {
            return DB::transaction(function () use (
                $organization, $user, $recommendationId, $decision,
                $reason, $adjustedStartAt, $adjustedEndAt,
            ) {
                $created = RecommendationDecision::create([
                    'recommendation_id' => $recommendationId,
                    'decision' => $decision,
                    'decided_by_id' => $user->id,
                    'reason' => $reason,
                    'adjusted_start_at' => $adjustedStartAt,
                    'adjusted_end_at' => $adjustedEndAt,
                ]);

                $action = match ($decision) {
                    RecommendationDecisionType::Accepted => 'recommendation.accepted',
                    RecommendationDecisionType::Rejected => 'recommendation.rejected',
                    RecommendationDecisionType::Partial => 'recommendation.partially_adopted',
                };

                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: $action,
                    entityType: 'RecommendationDecision',
                    entityId: $created->id,
                    organizationId: $organization->id,
                    metadata: [
                        'reason' => $reason,
                        'adjustedStartAt' => $adjustedStartAt?->toIso8601String(),
                        'adjustedEndAt' => $adjustedEndAt?->toIso8601String(),
                    ],
                ));

                return $created;
            });
        } finally {
            $this->currentOrganization->set($previousOrganization);
        }
    }
}
