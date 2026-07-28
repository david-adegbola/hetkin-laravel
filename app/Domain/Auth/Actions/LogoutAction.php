<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Audit entry only — Auth::guard('web')->logout(), session invalidation,
 * and CSRF regeneration stay in the controller (HTTP-layer concerns, and
 * this must run BEFORE the guard logs out so $user is still available).
 */
class LogoutAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'auth.logout',
                entityType: 'User',
                entityId: $user->id,
            ));
        });
    }
}
