<?php

namespace App\Domain\Organizations;

use App\Domain\Organizations\Models\Organization;

/**
 * Holds "which organization is this request scoped to," read by
 * App\Domain\Organizations\Scopes\OrganizationScope on every query against
 * a model using the BelongsToOrganization trait. The Laravel-idiomatic
 * counterpart to the Next.js app's orgScopedDb(organizationId) — instead of
 * passing a scoped Prisma client through every call site, this is bound
 * once as a container singleton and consulted implicitly by the ORM layer.
 *
 * Nothing binds a value into this today — Stage 3 delivers the mechanism;
 * a future stage's portal middleware calls set() after resolving the
 * request's organization via OrganizationContextResolver, the same way
 * App\Http\Middleware\UseSessionRealm exists in Stage 1 before anything
 * used the admin realm it swaps in for.
 *
 * Safe as a plain per-request singleton for the same reason
 * MIGRATION_PLAN.md ADR-12 gives for UseSessionRealm's config mutation:
 * correctness depends on a fresh application container per HTTP request
 * (true under standard php-fpm/CGI, NOT true under Octane or another
 * long-running-worker mode without additional care) — revisit together
 * with ADR-12 if Octane is ever adopted.
 */
final class CurrentOrganization
{
    private ?Organization $organization = null;

    public function set(?Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }
}
