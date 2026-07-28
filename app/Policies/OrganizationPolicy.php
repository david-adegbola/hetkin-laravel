<?php

namespace App\Policies;

use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;

/**
 * Membership is the only access boundary here — matches v2's current
 * behavior exactly: no code path in the Next.js app gates a portal action
 * by OrgRole, only by whether a Member row exists at all
 * (MIGRATION_PLAN.md ADR-25). Registered in AppServiceProvider via
 * Gate::policy().
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return Member::isMemberOf($user, $organization->id);
    }
}
