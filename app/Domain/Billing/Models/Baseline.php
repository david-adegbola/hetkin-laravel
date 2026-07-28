<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BaselineStatus;
use App\Domain\Organizations\Concerns\BelongsToOrganization;
use App\Models\AdminUser;
use Database\Factories\BaselineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `Baseline` Prisma model. Locked and
 * effectively immutable once `status = LOCKED` — application code must
 * never update a LOCKED baseline's cost/assumption fields; see
 * App\Domain\Billing\Actions\LockBaselineAction. Has a direct
 * `organization_id` and is in v2's RLS policy list, so it gets
 * BelongsToOrganization.
 *
 * No `updated_at` — see the creating migration's comment.
 */
#[Fillable([
    'organization_id', 'methodology_version', 'status', 'assumptions',
    'baseline_annual_cost_cents', 'locked_at', 'locked_by_admin_user_id',
])]
class Baseline extends Model
{
    /** @use HasFactory<BaselineFactory> */
    use BelongsToOrganization, HasFactory, HasUlids;

    public $timestamps = false;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): BaselineFactory
    {
        return BaselineFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => BaselineStatus::class,
            'assumptions' => 'array',
            'baseline_annual_cost_cents' => 'integer',
            'locked_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'locked_by_admin_user_id');
    }

    /**
     * @return HasMany<SavingsAssessment, $this>
     */
    public function savingsAssessments(): HasMany
    {
        return $this->hasMany(SavingsAssessment::class);
    }

    /**
     * @return HasMany<VerificationRecord, $this>
     */
    public function verificationRecords(): HasMany
    {
        return $this->hasMany(VerificationRecord::class);
    }
}
