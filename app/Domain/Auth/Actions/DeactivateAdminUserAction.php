<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Exceptions\AdminTeamActionException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's deactivateAdminUser() Server Action exactly (src/
 * app/admin/(protected)/team/actions.ts). ADMIN only.
 *
 * Self-deactivation is explicitly and server-side blocked — the exact error
 * message below matches v2's. Deactivation takes effect on the target's
 * very next request: App\Http\Middleware\EnsureAdminActive re-checks
 * `active` on every admin request, the same "layout-level double
 * enforcement" v2's own comment describes.
 */
class DeactivateAdminUserAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws AdminTeamActionException
     */
    public function execute(AdminUser $target, AdminUser $actor): AdminUser
    {
        if ($target->id === $actor->id) {
            throw new AdminTeamActionException("You can't deactivate your own account.");
        }

        return DB::transaction(function () use ($target, $actor) {
            $target->update(['active' => false]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $actor->id,
                action: 'admin_user.deactivated',
                entityType: 'AdminUser',
                entityId: $target->id,
            ));

            return $target;
        });
    }
}
