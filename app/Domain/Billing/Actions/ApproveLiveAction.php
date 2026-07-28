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
 * Ports the Next.js app's approveLive() Server Action exactly
 * (src/app/admin/(protected)/verification/actions.ts) — the second and
 * final step through shadow review; only accepts a record already in
 * SHADOW_CONFIRMED (a silent no-op otherwise), so nothing can jump
 * straight from a freshly-computed provisional number to LIVE_APPROVED —
 * the status App\Domain\Billing\Actions\GenerateInvoiceAction requires —
 * without a human looking at it twice.
 */
class ApproveLiveAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $recordId, AdminUser $adminUser): VerificationRecord
    {
        /** @var VerificationRecord $record */
        $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)->findOrFail($recordId);

        if ($record->review_status !== VerificationReviewStatus::ShadowConfirmed) {
            return $record;
        }

        return DB::transaction(function () use ($record, $adminUser) {
            $record->update([
                'review_status' => VerificationReviewStatus::LiveApproved,
                'reviewed_by_admin_user_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'verification_record.live_approved',
                entityType: 'VerificationRecord',
                entityId: $record->id,
                organizationId: $record->organization_id,
            ));

            return $record->refresh();
        });
    }
}
