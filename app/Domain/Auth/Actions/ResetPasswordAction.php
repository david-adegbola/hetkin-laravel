<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;
use App\Notifications\PasswordResetCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Wraps Laravel's Password Broker for the actual reset. Token/expiry
 * validation is entirely the broker's responsibility (Password::reset()
 * returns Password::INVALID_TOKEN for both "wrong token" and "expired
 * token" — Laravel doesn't distinguish the two to the caller, which is
 * itself a deliberate security property: an attacker probing tokens can't
 * learn "expired" vs "never valid").
 *
 * On success: rehashes the password (via the User model's 'hashed' cast),
 * regenerates remember_token (invalidates any remember-me cookie in the
 * wild), stamps password_changed_at, and deletes every row this user has
 * in the `sessions` table — "invalidate existing sessions" from the brief.
 * There is no "current session" to preserve here (this flow is
 * unauthenticated by definition), and the brief is explicit: do not
 * automatically log the user in afterward.
 */
class ResetPasswordAction
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @throws ValidationException
     */
    public function execute(string $token, string $email, string $password): void
    {
        $status = Password::broker('users')->reset(
            ['token' => $token, 'email' => $email, 'password' => $password, 'password_confirmation' => $password],
            function (User $user, string $password) {
                DB::transaction(function () use ($user, $password) {
                    $user->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                        'password_changed_at' => now(),
                    ])->save();

                    SessionRecord::query()->where('user_id', $user->id)->delete();

                    $this->auditLogService->record(new AuditEvent(
                        actorType: AuditActorType::User,
                        actorId: $user->id,
                        action: 'password.reset_completed',
                        entityType: 'User',
                        entityId: $user->id,
                    ));
                });

                $user->notify(new PasswordResetCompleted);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }
}
