<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's lockBaseline() Server Action exactly
 * (src/app/admin/(protected)/baselines/actions.ts) — "the one write in
 * this whole codebase that's deliberately gated behind a human admin
 * action rather than automated" (source comment). Idempotent: locking an
 * already-LOCKED baseline is a silent no-op, matching v2.
 *
 * Admin-triggered and genuinely cross-tenant (an admin isn't "in" any one
 * organization), so — unlike every customer-portal Action so far — this
 * bypasses App\Domain\Organizations\Scopes\OrganizationScope explicitly
 * rather than binding App\Domain\Organizations\CurrentOrganization, the
 * same way v2's adminScopedDb() reads across every org. This Action has no
 * controller/route wired to it yet (admin console lands in Stage 9) — see
 * MIGRATION_PLAN.md's Stage 7 ADR.
 */
class LockBaselineAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $baselineId, AdminUser $adminUser): Baseline
    {
        /** @var Baseline $baseline */
        $baseline = Baseline::withoutGlobalScope(OrganizationScope::class)->findOrFail($baselineId);

        if ($baseline->status === BaselineStatus::Locked) {
            return $baseline;
        }

        return DB::transaction(function () use ($baseline, $adminUser) {
            $baseline->update([
                'status' => BaselineStatus::Locked,
                'locked_at' => now(),
                'locked_by_admin_user_id' => $adminUser->id,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'baseline.locked',
                entityType: 'Baseline',
                entityId: $baseline->id,
                organizationId: $baseline->organization_id,
                metadata: ['methodologyVersion' => $baseline->methodology_version],
            ));

            return $baseline->refresh();
        });
    }
}
