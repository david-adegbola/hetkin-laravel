<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Authenticated "change password" — current-password and strength/
 * confirmation checks already happened in
 * App\Http\Requests\Settings\UpdatePasswordRequest before this runs.
 *
 * Session regeneration for the CURRENT request is the controller's job
 * (it needs the live Illuminate\Http\Request, an HTTP-layer concern this
 * Action deliberately doesn't take a dependency on — keeps it unit-testable
 * without a request). Every OTHER session this user has is invalidated
 * here via LogoutOtherDevicesAction, same mechanism "Log out other
 * devices" uses in Settings\SessionController.
 */
class ChangePasswordAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LogoutOtherDevicesAction $logoutOtherDevicesAction,
    ) {}

    public function execute(User $user, string $newPassword, string $currentSessionId): void
    {
        $otherSessionsRevoked = DB::transaction(function () use ($user, $newPassword, $currentSessionId) {
            $user->forceFill(['password' => $newPassword, 'password_changed_at' => now()])->save();

            $revoked = $this->logoutOtherDevicesAction->execute($user, $currentSessionId);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'password.changed',
                entityType: 'User',
                entityId: $user->id,
            ));

            return $revoked;
        });

        // The password is already changed above — a failure to send this
        // courtesy confirmation email must never turn that success into a
        // 500 for the user. Same posture as every other post-hoc
        // notification in this app; see SubmitPilotApplicationAction's
        // docblock for the incident that established the pattern.
        try {
            $user->notify(new PasswordChanged(hadOtherActiveSessions: $otherSessionsRevoked > 0));
        } catch (Throwable $e) {
            Log::error('[auth] failed to send password-changed notification', ['exception' => $e, 'userId' => $user->id]);
        }
    }
}
