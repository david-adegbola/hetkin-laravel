<?php

namespace App\Policies;

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Models\User;

/**
 * Same shape as MemberPolicy: visible to any member of the organization
 * the invitation belongs to, not just its inviter — matches the portal
 * "team" page (a later stage) listing every pending invitation to any
 * member who can see the team roster at all.
 */
class InvitationPolicy
{
    public function view(User $user, Invitation $invitation): bool
    {
        return Member::isMemberOf($user, $invitation->organization_id);
    }
}
