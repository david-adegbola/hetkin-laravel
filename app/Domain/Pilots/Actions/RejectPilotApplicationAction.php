<?php

namespace App\Domain\Pilots\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's rejectPilotApplication() Server Action exactly
 * (src/app/admin/(protected)/pilots/actions.ts). No note/reason field is
 * captured — v2's admin UI has no input for one — and no email is sent on
 * rejection. No idempotency guard, matching v2 (see
 * ApprovePilotApplicationAction's docblock for the same caveat).
 */
class RejectPilotApplicationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(PilotApplication $application, AdminUser $adminUser): PilotApplication
    {
        return DB::transaction(function () use ($application, $adminUser) {
            $application->update([
                'status' => PilotApplicationStatus::Rejected,
                'reviewed_by_admin_user_id' => $adminUser->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'pilot_application.rejected',
                entityType: 'PilotApplication',
                entityId: $application->id,
            ));

            return $application->refresh();
        });
    }
}
