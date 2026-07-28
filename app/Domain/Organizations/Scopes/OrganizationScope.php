<?php

namespace App\Domain\Organizations\Scopes;

use App\Domain\Organizations\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LogicException;

/**
 * The Eloquent-layer tenant boundary — MIGRATION_PLAN.md ADR-8's "primary
 * enforcement mechanism," standing in for the Postgres RLS policies v2 has
 * written but not yet turned on (its own db-rls.ts documents FORCE ROW
 * LEVEL SECURITY as still pending a non-owner runtime DB role). RLS remains
 * a defense-in-depth layer to add later; this scope is not a placeholder
 * for it.
 *
 * FAILS CLOSED, deliberately stricter than v2's own current posture: a
 * query against a BelongsToOrganization model with no CurrentOrganization
 * bound throws immediately rather than silently returning every
 * organization's rows (which is what actually happens in v2 today whenever
 * code reaches for `db` — the raw, unscoped Prisma client — instead of
 * `orgScopedDb()`; nothing there prevents that mistake at the type level).
 * A genuinely cross-tenant query (system jobs iterating every
 * organization, an admin-context read) opts out explicitly via
 * `Model::withoutGlobalScope(OrganizationScope::class)`, the same
 * escape-hatch shape as Laravel's own SoftDeletingScope — a deliberate,
 * visible grant at the call site, never an accident.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = app(CurrentOrganization::class)->id();

        if ($organizationId === null) {
            throw new LogicException(sprintf(
                '%s is scoped to an organization (App\Domain\Organizations\Concerns\BelongsToOrganization), '.
                'but no CurrentOrganization is bound for this request. Either resolve one first '.
                '(App\Domain\Organizations\Services\OrganizationContextResolver) or, for a genuinely '.
                'cross-tenant query, opt out explicitly: %s::withoutGlobalScope(%s::class).',
                $model::class,
                class_basename($model),
                self::class,
            ));
        }

        $builder->where($model->qualifyColumn('organization_id'), $organizationId);
    }
}
