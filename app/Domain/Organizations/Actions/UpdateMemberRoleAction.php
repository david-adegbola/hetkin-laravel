<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Member;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's updateMemberRole() Server Action exactly
 * (src/app/admin/(protected)/organizations/[id]/actions.ts). ADMIN/SUPPORT
 * — enforced by the route's `admin.role:ADMIN,SUPPORT` middleware.
 *
 * No-op (no write, no audit entry) if the requested role equals the
 * member's current role.
 */
class UpdateMemberRoleAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws OrganizationActionException
     */
    public function execute(Member $member, AdminUser $adminUser, string $role): Member
    {
        $newRole = OrgRole::tryFrom($role);

        if ($newRole === null) {
            throw new OrganizationActionException('Invalid role.');
        }

        if ($newRole === $member->role) {
            return $member;
        }

        $fromRole = $member->role;

        return DB::transaction(function () use ($member, $adminUser, $newRole, $fromRole) {
            $member->update(['role' => $newRole]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'member.role_changed',
                entityType: 'Member',
                entityId: $member->id,
                organizationId: $member->organization_id,
                metadata: ['from' => $fromRole->value, 'to' => $newRole->value],
            ));

            return $member;
        });
    }
}
