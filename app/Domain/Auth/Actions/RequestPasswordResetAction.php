<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Wraps Laravel's own Password Broker — this class does not generate,
 * store, or validate tokens itself. A request for a non-existent email
 * produces the same broker status either way to the CALLER (see
 * App\Http\Controllers\Auth\PasswordResetLinkController, which returns an
 * identical generic response regardless of $status — "never reveal whether
 * an email exists"), but there is no real user to attribute an audit entry
 * or send a notification to for that case, so neither happens.
 *
 * Passing a $callback to sendResetLink() is an EITHER/OR with the broker's
 * own default behavior, not an additional hook: per
 * Illuminate\Auth\Passwords\PasswordBroker::sendResetLink(), supplying a
 * callback means the broker skips its own `$user->
 * sendPasswordResetNotification($token)` call entirely and delegates that
 * responsibility to the callback — so the callback below must send it
 * explicitly, not just piggyback on a send that (with a callback present)
 * never happens.
 */
class RequestPasswordResetAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function execute(string $email): string
    {
        $status = Password::broker('users')->sendResetLink(
            ['email' => $email],
            function (User $user, string $token) {
                DB::transaction(function () use ($user) {
                    $this->auditLogService->record(new AuditEvent(
                        actorType: AuditActorType::User,
                        actorId: $user->id,
                        action: 'password.reset_requested',
                        entityType: 'User',
                        entityId: $user->id,
                    ));
                });

                $user->sendPasswordResetNotification($token);
            },
        );

        return $status;
    }
}
