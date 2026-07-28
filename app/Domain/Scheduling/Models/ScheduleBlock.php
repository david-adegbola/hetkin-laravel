<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Models\Recommendation;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `ScheduleBlock` Prisma model. Deliberately NOT
 * using BelongsToOrganization — see the creating migration's comment and
 * MIGRATION_PLAN.md ADR-29/32.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'schedule_id', 'site_id', 'asset_label', 'start_at',
    'end_at', 'description', 'estimated_kwh',
])]
class ScheduleBlock extends Model
{
    /** @use HasFactory<ScheduleBlockFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): ScheduleBlockFactory
    {
        return ScheduleBlockFactory::new();
    }

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'estimated_kwh' => 'decimal:3',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductionSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'schedule_id');
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'schedule_block_id');
    }
}
