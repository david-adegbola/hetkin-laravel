<?php

namespace App\Domain\Auth\DTOs;

use App\Models\AdminUser;

/**
 * Result of App\Domain\Auth\Actions\CreateAdminUserAction — carries the
 * plaintext temp password back to the controller for one-time UI display.
 * Never persisted, logged, or emailed anywhere past this point (mirrors
 * v2's team/CreateAdminUserForm.tsx: shown once, gone if dismissed).
 */
final class CreateAdminUserResult
{
    public function __construct(
        public readonly AdminUser $adminUser,
        public readonly string $tempPassword,
    ) {}
}
