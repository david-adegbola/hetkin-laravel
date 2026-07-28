<?php

namespace App\Policies;

use App\Domain\Auth\Models\SessionRecord;
use App\Models\User;

/**
 * Authorizes session-revocation — the only access rule this module needs
 * a Policy for: a session row belongs to exactly one user, and only that
 * user may revoke it. Registered in AppServiceProvider via Gate::policy().
 */
class SessionPolicy
{
    public function revoke(User $user, SessionRecord $sessionRecord): bool
    {
        return $sessionRecord->user_id === $user->id;
    }
}
