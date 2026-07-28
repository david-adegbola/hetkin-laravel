<?php

namespace App\Policies;

use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;

/**
 * MeteringPoint has no organization_id of its own (see that model's
 * docblock) — authorization goes through its site's organization, same
 * boundary v2 itself uses. Queries Site directly with the scope bypassed,
 * rather than via `$meteringPoint->site` (which would run through Site's
 * fail-closed BelongsToOrganization scope): a Policy has to be checkable
 * before any CurrentOrganization is necessarily bound — authorization is
 * often exactly what determines whether to bind one in the first place, so
 * requiring it as a precondition here would be circular. Same reasoning as
 * Member::isMemberOf()'s own explicit opt-out.
 */
class MeteringPointPolicy
{
    public function view(User $user, MeteringPoint $meteringPoint): bool
    {
        $organizationId = Site::withoutGlobalScope(OrganizationScope::class)
            ->whereKey($meteringPoint->site_id)
            ->value('organization_id');

        return $organizationId !== null && Member::isMemberOf($user, $organizationId);
    }
}
