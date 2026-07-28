<?php

namespace App\Domain\Organizations\Services;

use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;

/**
 * Ports the Next.js app's requirePortalContext() (src/lib/portal-context.ts)
 * resolution logic exactly: prefer the session's remembered "active
 * organization," falling back to the user's first membership (by creation
 * order) if there isn't one or it no longer resolves. Most pilot accounts
 * start with exactly one organization, so this covers the common case
 * without forcing an org-switcher UI before one exists.
 *
 * Deliberately trusts `$sessionActiveOrganizationId` without re-checking
 * membership here — same as v2, which does a plain
 * `organization.findUnique({ where: { id: activeOrgId } })` with no
 * membership join. That's safe there because Better Auth's organization
 * plugin only ever WRITES activeOrganizationId through its own
 * setActive API, which itself checks membership before accepting a value —
 * the trust boundary is at write time, not read time. This port carries
 * the same assumption forward: nothing yet writes an active-organization
 * value into the session (no "switch organization" action exists before
 * Stage 5+), so there is no untrusted writer to guard against today. If a
 * future stage adds one, it must check membership before storing the
 * value, not this method before reading it.
 *
 * Pure resolution only — does not bind the result into CurrentOrganization
 * itself. A future stage's portal middleware is expected to call this once
 * per request and then `app(CurrentOrganization::class)->set($organization)`,
 * mirroring how App\Http\Middleware\UseSessionRealm exists before anything
 * in Stage 1 used the realm it swaps in.
 */
class OrganizationContextResolver
{
    public function resolve(User $user, ?string $sessionActiveOrganizationId): ?Organization
    {
        $organization = $sessionActiveOrganizationId
            ? Organization::query()->find($sessionActiveOrganizationId)
            : null;

        if ($organization) {
            return $organization;
        }

        // Bootstrap query: which organization does this user belong to, at
        // all — there is no organization to scope by yet, so this
        // explicitly opts out of OrganizationScope rather than throwing.
        $membership = Member::withoutGlobalScope(OrganizationScope::class)
            ->where('user_id', $user->id)
            ->oldest('created_at')
            ->with('organization')
            ->first();

        return $membership?->organization;
    }
}
