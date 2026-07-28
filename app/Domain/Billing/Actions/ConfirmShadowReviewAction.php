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
 * Ports the Next.js app's confirmShadowReview() Server Action exactly
 * (src/app/admin/(protected)/verification/actions.ts) — the first of two
 * sequential admin moves through shadow review; only accepts a record
 * still in SHADOW_PENDING_REVIEW, a silent no-op otherwise. Role-gated to
 * ADMIN/VERIFICATION_ANALYST in v2; this port has no admin controller
 * wired to it yet (Stage 9), so the role check isn't enforced here — the
 * caller is expected to check `$adminUser->role` before calling, the same
 * way the eventual controller will.
 */
class ConfirmShadowReviewAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $recordId, AdminUser $adminUser): VerificationRecord
    {
        /** @var VerificationRecord $record */
        $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)->findOrFail($recordId);

        if ($record->review_status !== VerificationReviewStatus::ShadowPendingReview) {
            return $record;
        }

        return DB::transaction(function () use ($record, $adminUser) {
            $record->update([
                'review_status' => VerificationReviewStatus::ShadowConfirmed,
                'reviewed_by_admin_user_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'verification_record.shadow_confirmed',
                entityType: 'VerificationRecord',
                entityId: $record->id,
                organizationId: $record->organization_id,
            ));

            return $record->refresh();
        });
    }
}
