<?php

namespace App\Domain\Organizations\Concerns;

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model (Member, Invitation here; Site,
 * ProductionSchedule, etc. from Stage 4 onward) — adds the
 * App\Domain\Organizations\Scopes\OrganizationScope global scope and
 * auto-fills `organization_id` on create from the bound
 * CurrentOrganization, mirroring how every write in v2 already runs
 * through an orgScopedDb(organizationId) client that injects the same
 * value via `set_config('app.current_organization_id', ...)` for its RLS
 * policies. NOT applied to Organization itself — that model is the tenant,
 * not a tenant-owned row.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model): void {
            if (! $model->organization_id) {
                $model->organization_id = app(CurrentOrganization::class)->id();
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
