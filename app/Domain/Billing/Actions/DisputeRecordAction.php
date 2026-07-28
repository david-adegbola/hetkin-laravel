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
 * Ports the Next.js app's disputeRecord() Server Action exactly
 * (src/app/admin/(protected)/verification/actions.ts) — the escape hatch
 * from either shadow-review state when the numbers look wrong. Accepts
 * anything except LIVE_APPROVED or already-DISPUTED (a silent no-op for
 * those two).
 */
class DisputeRecordAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $recordId, AdminUser $adminUser): VerificationRecord
    {
        /** @var VerificationRecord $record */
        $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)->findOrFail($recordId);

        if (in_array($record->review_status, [VerificationReviewStatus::LiveApproved, VerificationReviewStatus::Disputed], true)) {
            return $record;
        }

        return DB::transaction(function () use ($record, $adminUser) {
            $record->update([
                'review_status' => VerificationReviewStatus::Disputed,
                'reviewed_by_admin_user_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'verification_record.disputed',
                entityType: 'VerificationRecord',
                entityId: $record->id,
                organizationId: $record->organization_id,
            ));

            return $record->refresh();
        });
    }
}
