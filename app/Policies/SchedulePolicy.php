<?php

namespace App\Policies;

use App\Domain\Organizations\Models\Member;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Models\User;

/**
 * Membership-gated, same shape as OrganizationPolicy — see that policy's
 * docblock for why role isn't part of the check.
 */
class SchedulePolicy
{
    public function view(User $user, ProductionSchedule $schedule): bool
    {
        return Member::isMemberOf($user, $schedule->organization_id);
    }
}
