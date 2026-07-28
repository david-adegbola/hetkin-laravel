<?php

namespace App\Policies;

use App\Domain\Organizations\Models\Member;
use App\Models\User;

/**
 * A membership row is visible to any OTHER member of the same
 * organization (the portal "team" page, a later stage, lists every
 * teammate) — gated by the viewer's own membership, not by whose row it
 * is. See OrganizationPolicy's docblock for why this stops at membership
 * and doesn't add OrgRole-based gating.
 */
class MemberPolicy
{
    public function view(User $user, Member $member): bool
    {
        return Member::isMemberOf($user, $member->organization_id);
    }
}
