<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\SavingsAssessmentStatus;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use Database\Factories\SavingsAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mirrors the Next.js app's `SavingsAssessment` Prisma model. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'baseline_id', 'optimization_run_id', 'status', 'current_annual_cost_cents',
    'optimized_annual_cost_cents', 'estimated_annual_savings_cents',
    'hetkin_share_cents', 'net_customer_savings_cents', 'is_provisional',
    'sample_day_count', 'period_start', 'period_end', 'report_pdf_url',
    'report_snapshot', 'generated_at',
])]
class SavingsAssessment extends Model
{
    /** @use HasFactory<SavingsAssessmentFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saving(function (self $assessment): void {
            // This is a denormalized convenience column, never an
            // independently editable financial figure. Keeping it in sync
            // at the model boundary protects every caller, not just the
            // generation action or report renderer.
            $assessment->net_customer_savings_cents = $assessment->netCustomerSavingsCents();
        });
    }

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): SavingsAssessmentFactory
    {
        return SavingsAssessmentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => SavingsAssessmentStatus::class,
            'is_provisional' => 'boolean',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'generated_at' => 'datetime',
            'report_snapshot' => 'array',
        ];
    }

    public function netCustomerSavingsCents(): int
    {
        return (int) $this->estimated_annual_savings_cents - (int) $this->hetkin_share_cents;
    }

    /**
     * @return BelongsTo<Baseline, $this>
     */
    public function baseline(): BelongsTo
    {
        return $this->belongsTo(Baseline::class);
    }

    /** @return BelongsTo<OptimizationRun, $this> */
    public function optimizationRun(): BelongsTo
    {
        return $this->belongsTo(OptimizationRun::class);
    }
}
