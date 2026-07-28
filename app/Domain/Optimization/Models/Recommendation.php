<?php

namespace App\Domain\Optimization\Models;

use App\Domain\Optimization\Enums\DeliveryRisk;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mirrors the Next.js app's `Recommendation` Prisma model. Deliberately
 * NOT using BelongsToOrganization — see the creating migration's comment.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'optimization_run_id', 'schedule_block_id', 'proposed_start_at', 'proposed_end_at',
    'projected_savings_cents', 'delivery_risk', 'rationale', 'business_impact',
])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): RecommendationFactory
    {
        return RecommendationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'proposed_start_at' => 'datetime',
            'proposed_end_at' => 'datetime',
            'delivery_risk' => DeliveryRisk::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OptimizationRun, $this>
     */
    public function optimizationRun(): BelongsTo
    {
        return $this->belongsTo(OptimizationRun::class, 'optimization_run_id');
    }

    /**
     * @return BelongsTo<ScheduleBlock, $this>
     */
    public function scheduleBlock(): BelongsTo
    {
        return $this->belongsTo(ScheduleBlock::class, 'schedule_block_id');
    }

    /**
     * @return HasOne<RecommendationDecision, $this>
     */
    public function decision(): HasOne
    {
        return $this->hasOne(RecommendationDecision::class, 'recommendation_id');
    }
}
