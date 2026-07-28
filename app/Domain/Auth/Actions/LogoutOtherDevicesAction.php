<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes every `sessions` row for $user except $currentSessionId — used
 * both by the explicit "Log out other devices" settings action and, as an
 * internal step, by ChangePasswordAction (password brief: "invalidate
 * other sessions where supported"). Returns the number of sessions
 * removed so callers can decide what to tell the user (e.g. skip the
 * "other devices signed out" notification line when the count is zero).
 *
 * Deliberately does NOT send a notification itself — see this class's
 * multiple callers above, which want different (or no) copy for the same
 * underlying action. Notification dispatch is the caller's decision.
 */
class LogoutOtherDevicesAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function execute(User $user, string $currentSessionId): int
    {
        return DB::transaction(function () use ($user, $currentSessionId) {
            $deleted = SessionRecord::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();

            if ($deleted > 0) {
                $this->auditLogService->record(new AuditEvent(
                    actorType: AuditActorType::User,
                    actorId: $user->id,
                    action: 'session.logged_out_other_devices',
                    entityType: 'User',
                    entityId: $user->id,
                    metadata: ['sessions_revoked' => $deleted],
                ));
            }

            return $deleted;
        });
    }
}
