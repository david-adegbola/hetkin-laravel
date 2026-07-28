<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Credential verification, rate limiting, and Auth::attempt() itself stay
 * in App\Http\Requests\Auth\LoginRequest — that's the idiomatic Laravel/
 * Breeze location for "is this login allowed to proceed" and there's no
 * reason to relocate a pattern this codebase (and most Laravel apps)
 * already gets right. What this Action owns is the one piece of business
 * logic that happens strictly AFTER a successful attempt: the audit entry.
 * Session regeneration/CSRF-token handling stay in the controller — they
 * need the live Illuminate\Http\Request, an HTTP-layer concern.
 */
class LoginAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'auth.login',
                entityType: 'User',
                entityId: $user->id,
            ));
        });
    }
}
