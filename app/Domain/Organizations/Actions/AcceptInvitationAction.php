<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Exceptions\InvitationException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's acceptInvitation() Server Action exactly
 * (src/app/invite/[invitationId]/actions.ts). "Intentionally re-defensive
 * about the invitation's validity even though the page also checks it —
 * the page's check is for UX, this one is what actually gates the write"
 * (source comment) — every check below (status, expiry, email match) is
 * ported verbatim for that reason, not just the happy path.
 *
 * The email-match check is the real authorization mechanism here, not the
 * invitation token alone: whoever accepts has to be signed in as (or just
 * created an account with) the exact address the invite was sent to.
 *
 * A no-op if the user is already a member (mirrors v2's
 * `if (!existingMember)` guard) — accepting the same invitation twice, or
 * an already-a-member accepting a stray link, doesn't throw or duplicate
 * a Member row.
 */
class AcceptInvitationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws InvitationException
     */
    public function execute(Invitation $invitation, User $user): void
    {
        if ($invitation->status !== 'pending') {
            throw new InvitationException('This invitation has already been used.');
        }
        if ($invitation->expires_at->isPast()) {
            throw new InvitationException('This invitation has expired.');
        }
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw new InvitationException(
                'This invitation was sent to a different email address than the one you signed in with.',
            );
        }

        if (Member::isMemberOf($user, $invitation->organization_id)) {
            return;
        }

        DB::transaction(function () use ($invitation, $user) {
            Member::create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
            ]);

            $invitation->update(['status' => 'accepted']);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'invitation.accepted',
                entityType: 'Invitation',
                entityId: $invitation->id,
                organizationId: $invitation->organization_id,
            ));
        });
    }
}
