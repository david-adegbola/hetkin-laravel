<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's reactivateAdminUser() Server Action exactly (src/
 * app/admin/(protected)/team/actions.ts). ADMIN only.
 *
 * Unlike App\Domain\Auth\Actions\ChangeAdminRoleAction, there's no no-op
 * guard here — reactivating an already-active admin still writes the
 * update and an audit entry, matching v2 exactly (no self-referential
 * guard needed either: only an active admin could call this).
 */
class ReactivateAdminUserAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(AdminUser $target, AdminUser $actor): AdminUser
    {
        return DB::transaction(function () use ($target, $actor) {
            $target->update(['active' => true]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $actor->id,
                action: 'admin_user.reactivated',
                entityType: 'AdminUser',
                entityId: $target->id,
            ));

            return $target;
        });
    }
}
