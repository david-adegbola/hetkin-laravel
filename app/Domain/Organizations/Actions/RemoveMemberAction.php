<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Models\Member;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's removeMember() Server Action exactly
 * (src/app/admin/(protected)/organizations/[id]/actions.ts). ADMIN/SUPPORT
 * — enforced by the route's `admin.role:ADMIN,SUPPORT` middleware.
 *
 * A hard delete with no guard rails beyond the member existing (route-model
 * binding's own 404) — v2 has no check preventing this from removing an
 * org's last member/last OWNER, and this port doesn't add one either. Only
 * the Member (pivot) row is deleted; the underlying User account, its
 * sessions, and its other org memberships are untouched.
 */
class RemoveMemberAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(Member $member, AdminUser $adminUser): void
    {
        DB::transaction(function () use ($member, $adminUser) {
            $memberId = $member->id;
            $organizationId = $member->organization_id;
            $userId = $member->user_id;
            $role = $member->role;

            $member->delete();

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'member.removed',
                entityType: 'Member',
                entityId: $memberId,
                organizationId: $organizationId,
                metadata: ['userId' => $userId, 'role' => $role->value],
            ));
        });
    }
}
