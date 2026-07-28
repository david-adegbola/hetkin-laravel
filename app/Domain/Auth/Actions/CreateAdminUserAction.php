<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Auth\DTOs\CreateAdminUserResult;
use App\Domain\Auth\Enums\AdminRole;
use App\Domain\Auth\Exceptions\AdminTeamActionException;
use App\Models\AdminUser;
use App\Notifications\AdminAccountCreated;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's createAdminUser() Server Action exactly (src/app/
 * admin/(protected)/team/actions.ts). ADMIN only — enforced by the route's
 * `admin.role:ADMIN` middleware.
 *
 * v2 creates the AdminUser via a Better Auth server call (which also writes
 * a separate AdminAccount credential row), then overwrites role/active/name
 * in the same transaction as the audit write. This port has no separate
 * credential table (MIGRATION_PLAN.md ADR-2 — the password hash lives
 * directly on AdminUser), so it's a single `AdminUser::create()` doing what
 * both v2 steps did together.
 *
 * The temp password is 32 base64url characters from 24 random bytes — sized
 * the same way v2's `generateTempPassword()` is, comfortably over this
 * app's own admin password-strength floor. It is never written to the audit
 * log or emailed; it's returned once for the creating admin's own UI to
 * display, then gone.
 */
class CreateAdminUserAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws AdminTeamActionException
     */
    public function execute(AdminUser $creator, string $name, string $email, string $role): CreateAdminUserResult
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if ($name === '') {
            throw new AdminTeamActionException('Name is required.');
        }
        if ($email === '' || ! str_contains($email, '@')) {
            throw new AdminTeamActionException('A valid email is required.');
        }

        $roleEnum = AdminRole::tryFrom($role);
        if ($roleEnum === null) {
            throw new AdminTeamActionException('Invalid role.');
        }

        if (AdminUser::where('email', $email)->exists()) {
            throw new AdminTeamActionException('An admin account with this email already exists.');
        }

        $tempPassword = self::generateTempPassword();

        $adminUser = DB::transaction(function () use ($creator, $name, $email, $roleEnum, $tempPassword) {
            $adminUser = AdminUser::create([
                'name' => $name,
                'email' => $email,
                'password' => $tempPassword,
                'role' => $roleEnum,
                'active' => true,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $creator->id,
                action: 'admin_user.created',
                entityType: 'AdminUser',
                entityId: $adminUser->id,
                metadata: ['email' => $email, 'role' => $roleEnum->value],
            ));

            return $adminUser;
        });

        $adminUser->notify(new AdminAccountCreated($name, $roleEnum, $creator->name));

        return new CreateAdminUserResult($adminUser, $tempPassword);
    }

    private static function generateTempPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }
}
