<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Models\Invitation;
use App\Models\AdminUser;
use App\Notifications\InvitationResent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ports the Next.js app's resendInvitation() Server Action exactly
 * (src/app/admin/(protected)/organizations/[id]/actions.ts). ADMIN/SUPPORT
 * — enforced by the route's `admin.role:ADMIN,SUPPORT` middleware.
 *
 * Silent no-op (no email, no audit entry, no error) if the invitation is no
 * longer pending — unlike this domain's other no-op guards, this is a
 * status check, not an equality check, and it fails quietly by design
 * (source has no error path for "already accepted"). Does NOT regenerate
 * anything: no new Invitation row, no `expires_at` extension, no `status`
 * change — purely "re-send the same email for the same still-pending row."
 *
 * v2's source writes this audit entry outside any transaction (there's no
 * domain-state write to keep atomic with it, and the email side effect
 * isn't DB-atomic anyway). App\Domain\Audit\Services\AuditLogService::
 * record() enforces an active transaction unconditionally though, so this
 * wraps the audit write alone in one — a mechanical necessity, not a
 * behavior change.
 */
class ResendInvitationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(Invitation $invitation, AdminUser $adminUser): void
    {
        if ($invitation->status !== 'pending') {
            return;
        }

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationResent($invitation, $invitation->organization->name));

        DB::transaction(function () use ($invitation, $adminUser) {
            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'invitation.resent',
                entityType: 'Invitation',
                entityId: $invitation->id,
                organizationId: $invitation->organization_id,
            ));
        });
    }
}
