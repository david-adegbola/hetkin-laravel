<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes exactly one session row belonging to $user. Authorization
 * (does this session actually belong to $user?) is the caller's
 * responsibility via SessionPolicy, not this class — but the delete query
 * below is ALSO scoped by user_id regardless, as a second, independent
 * guarantee that this action can never delete another user's session even
 * if it were ever called without the policy check.
 *
 * Deleting the row is immediate and sufficient: with SESSION_DRIVER=
 * database, the next request bearing that session's cookie finds no
 * matching row and is treated as an unauthenticated guest. No separate
 * "kill signal" mechanism is needed.
 */
class RevokeSessionAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function execute(User $user, string $sessionId): void
    {
        DB::transaction(function () use ($user, $sessionId) {
            SessionRecord::query()
                ->where('user_id', $user->id)
                ->where('id', $sessionId)
                ->delete();

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'session.revoked',
                entityType: 'User',
                entityId: $user->id,
                metadata: ['session_id' => $sessionId],
            ));
        });
    }
}
