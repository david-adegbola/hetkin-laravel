<?php

namespace App\Domain\Metering\Models;

use App\Domain\Metering\Enums\MeterDataSource;
use Database\Factories\MeteringPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `MeteringPoint` Prisma model. Deliberately NOT
 * using App\Domain\Organizations\Concerns\BelongsToOrganization — this
 * table has no `organization_id` column in the source schema, only
 * `site_id`; tenant scoping is via the `site` relation exclusively,
 * exactly matching v2's own RLS policy boundary (MIGRATION_PLAN.md
 * ADR-29). Every query for "this organization's metering points" goes
 * through an already-scoped Site, the same way v2's own code always
 * resolves a Site first.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'site_id', 'label', 'datahub_metering_point_id',
    'data_source', 'connection_healthy', 'last_reading_at',
])]
class MeteringPoint extends Model
{
    /** @use HasFactory<MeteringPointFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): MeteringPointFactory
    {
        return MeteringPointFactory::new();
    }

    protected function casts(): array
    {
        return [
            'data_source' => MeterDataSource::class,
            'connection_healthy' => 'boolean',
            'last_reading_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<MeterReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }
}
