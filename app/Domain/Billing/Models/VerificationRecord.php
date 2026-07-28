<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Models\AdminUser;
use Database\Factories\VerificationRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mirrors the Next.js app's `VerificationRecord` Prisma model. Monthly,
 * shadow-mode-first: `review_status` is what enforces "verification runs
 * in shadow mode before it can affect billing" as a data invariant — no
 * Invoice can be created from a VerificationRecord until review_status is
 * LIVE_APPROVED (enforced in App\Domain\Billing\Actions\
 * GenerateInvoiceAction, not by a DB constraint). Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'baseline_id', 'period_start', 'period_end',
    'baseline_cost_cents', 'actual_cost_cents', 'verified_savings_cents',
    'variance_percent', 'variance_flagged', 'methodology_version',
    'assumptions', 'review_status', 'reviewed_by_admin_user_id', 'reviewed_at',
])]
class VerificationRecord extends Model
{
    /** @use HasFactory<VerificationRecordFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): VerificationRecordFactory
    {
        return VerificationRecordFactory::new();
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'variance_percent' => 'decimal:2',
            'variance_flagged' => 'boolean',
            'assumptions' => 'array',
            'review_status' => VerificationReviewStatus::class,
            'reviewed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Baseline, $this>
     */
    public function baseline(): BelongsTo
    {
        return $this->belongsTo(Baseline::class);
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_user_id');
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
