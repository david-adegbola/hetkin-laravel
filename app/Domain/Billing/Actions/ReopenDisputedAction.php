<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's reopenDisputed() Server Action exactly
 * (src/app/admin/(protected)/verification/actions.ts). Sends a DISPUTED
 * record back to SHADOW_PENDING_REVIEW so it goes through both review
 * steps again from scratch — never straight to SHADOW_CONFIRMED or
 * LIVE_APPROVED. Only accepts a record currently DISPUTED (a silent no-op
 * otherwise). Does not recompute the underlying numbers — if the dispute
 * was caused by bad input data, re-running RunVerificationAction for that
 * period overwrites the figures (its upsert's update branch never touches
 * review_status) before this record is looked at again.
 *
 * v2 role-gates this to ADMIN only (stricter than the other three
 * shadow-review actions, which also allow VERIFICATION_ANALYST) — same
 * caveat as ConfirmShadowReviewAction: no controller enforces this yet.
 */
class ReopenDisputedAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $recordId, AdminUser $adminUser): VerificationRecord
    {
        /** @var VerificationRecord $record */
        $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)->findOrFail($recordId);

        if ($record->review_status !== VerificationReviewStatus::Disputed) {
            return $record;
        }

        return DB::transaction(function () use ($record, $adminUser) {
            $record->update([
                'review_status' => VerificationReviewStatus::ShadowPendingReview,
                'reviewed_by_admin_user_id' => null,
                'reviewed_at' => null,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'verification_record.reopened',
                entityType: 'VerificationRecord',
                entityId: $record->id,
                organizationId: $record->organization_id,
            ));

            return $record->refresh();
        });
    }
}
