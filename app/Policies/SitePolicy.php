<?php

namespace App\Policies;

use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\Models\Member;
use App\Models\User;

/**
 * Membership-gated, same shape as OrganizationPolicy — see that policy's
 * docblock for why role isn't part of the check.
 */
class SitePolicy
{
    public function view(User $user, Site $site): bool
    {
        return Member::isMemberOf($user, $site->organization_id);
    }
}
