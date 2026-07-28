<?php

namespace App\Domain\Metering\Models;

use App\Domain\Metering\Enums\MeterDataSource;
use Database\Factories\MeterReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `MeterReading` Prisma model — hourly
 * consumption. Not scoped by BelongsToOrganization for the same reason as
 * MeteringPoint (no `organization_id` column in the source; see that
 * model's docblock and MIGRATION_PLAN.md ADR-29).
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable(['metering_point_id', 'hour_start', 'consumption_kwh', 'source'])]
class MeterReading extends Model
{
    /** @use HasFactory<MeterReadingFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): MeterReadingFactory
    {
        return MeterReadingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'hour_start' => 'datetime',
            'consumption_kwh' => 'decimal:3',
            'source' => MeterDataSource::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MeteringPoint, $this>
     */
    public function meteringPoint(): BelongsTo
    {
        return $this->belongsTo(MeteringPoint::class);
    }
}
