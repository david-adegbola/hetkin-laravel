<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Scheduling\Enums\LabourClassification;
use Database\Factories\ScheduleBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `ScheduleBlock` Prisma model, plus
 * `labour_classification` — new product scope layered on top, not a v2
 * field (see MIGRATION_PLAN.md's Labour-Aware Shiftable Load
 * Classification ADR). Deliberately NOT using BelongsToOrganization —
 * see the creating migration's comment and MIGRATION_PLAN.md ADR-29/32.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'schedule_id', 'site_id', 'asset_label', 'start_at',
    'end_at', 'description', 'estimated_kwh', 'labour_classification',
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
            'labour_classification' => LabourClassification::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * The single, centralized eligibility check every consumer of "which
     * blocks can the optimizer consider" goes through — currently kWh
     * present + Unmanned, but written as one method precisely so a future
     * constraint (a maintenance window, a regulatory restriction,
     * production criticality) is one more condition here, not a second
     * copy of this filter scattered across call sites.
     */
    public function isEligibleForOptimization(): bool
    {
        return $this->estimated_kwh !== null
            && $this->labour_classification === LabourClassification::Unmanned;
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
