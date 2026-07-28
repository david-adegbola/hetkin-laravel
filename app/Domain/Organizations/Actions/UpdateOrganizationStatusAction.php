<?php

namespace App\Domain\Organizations\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Organization;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's updateOrganizationStatus() Server Action exactly
 * (src/app/admin/(protected)/organizations/[id]/actions.ts). ADMIN only —
 * enforced by the route's `admin.role:ADMIN` middleware, not here.
 *
 * No-op (no write, no audit entry) if the requested status equals the
 * organization's current status — resubmitting the same status silently
 * does nothing rather than erroring or duplicating an audit row.
 */
class UpdateOrganizationStatusAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws OrganizationActionException
     */
    public function execute(Organization $organization, AdminUser $adminUser, string $status): Organization
    {
        $newStatus = OrganizationStatus::tryFrom($status);

        if ($newStatus === null) {
            throw new OrganizationActionException('Invalid status.');
        }

        if ($newStatus === $organization->status) {
            return $organization;
        }

        $fromStatus = $organization->status;

        return DB::transaction(function () use ($organization, $adminUser, $newStatus, $fromStatus) {
            $organization->update(['status' => $newStatus]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'organization.status_changed',
                entityType: 'Organization',
                entityId: $organization->id,
                organizationId: $organization->id,
                metadata: ['from' => $fromStatus->value, 'to' => $newStatus->value],
            ));

            return $organization;
        });
    }
}
