<?php

namespace App\Domain\Optimization\Models;

use App\Domain\Optimization\Enums\OptimizationStatus;
use App\Domain\Optimization\Enums\OptimizationTrigger;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Domain\Scheduling\Models\ProductionSchedule;
use Database\Factories\OptimizationRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `OptimizationRun` Prisma model. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization — see the creating migration's comment.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'schedule_id', 'trigger', 'status',
    'methodology_version', 'started_at', 'completed_at', 'error_message',
])]
class OptimizationRun extends Model
{
    /** @use HasFactory<OptimizationRunFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): OptimizationRunFactory
    {
        return OptimizationRunFactory::new();
    }

    protected function casts(): array
    {
        return [
            'trigger' => OptimizationTrigger::class,
            'status' => OptimizationStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return HasMany<Recommendation, $this>
     */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class, 'optimization_run_id');
    }
}
