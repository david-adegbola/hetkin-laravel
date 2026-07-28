<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Auth\Exceptions\AdminTeamActionException;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's changeAdminRole() Server Action exactly (src/app/
 * admin/(protected)/team/actions.ts). ADMIN only.
 *
 * No-op (no write, no audit entry) if the requested role equals the
 * target's current role, same shape as
 * App\Domain\Organizations\Actions\UpdateOrganizationStatusAction.
 *
 * No self-referential guard, matching v2 exactly — v2 only prevents an
 * admin from changing their own role at the UI layer (the page doesn't
 * render the control for the actor's own row); the server action itself
 * has no check. This port doesn't add one either, to stay behavior-
 * identical; a controller/route calling this with the actor's own id would
 * succeed, same as a direct POST to v2's action would.
 */
class ChangeAdminRoleAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws AdminTeamActionException
     */
    public function execute(AdminUser $target, AdminUser $actor, string $role): AdminUser
    {
        $newRole = AdminRole::tryFrom($role);

        if ($newRole === null) {
            throw new AdminTeamActionException('Invalid role.');
        }

        if ($newRole === $target->role) {
            return $target;
        }

        $fromRole = $target->role;

        return DB::transaction(function () use ($target, $actor, $newRole, $fromRole) {
            $target->update(['role' => $newRole]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $actor->id,
                action: 'admin_user.role_changed',
                entityType: 'AdminUser',
                entityId: $target->id,
                metadata: ['from' => $fromRole->value, 'to' => $newRole->value],
            ));

            return $target;
        });
    }
}
