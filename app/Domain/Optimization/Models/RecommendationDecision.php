<?php

namespace App\Domain\Optimization\Models;

use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Models\User;
use Database\Factories\RecommendationDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `RecommendationDecision` Prisma model.
 * Deliberately NOT using BelongsToOrganization — see the creating
 * migration's comment. Exactly one per recommendation (unique constraint
 * on `recommendation_id`) — a product metric and a scheduler-trust
 * signal, never silently overwritten.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'recommendation_id', 'decided_by_id', 'decision',
    'reason', 'adjusted_start_at', 'adjusted_end_at',
])]
class RecommendationDecision extends Model
{
    /** @use HasFactory<RecommendationDecisionFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): RecommendationDecisionFactory
    {
        return RecommendationDecisionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'decision' => RecommendationDecisionType::class,
            'adjusted_start_at' => 'datetime',
            'adjusted_end_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Recommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(Recommendation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
