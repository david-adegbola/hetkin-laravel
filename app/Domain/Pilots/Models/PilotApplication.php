<?php

namespace App\Domain\Pilots\Models;

use App\Domain\Organizations\Enums\ProcessType;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Models\AdminUser;
use Database\Factories\PilotApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Mirrors the Next.js app's `PilotApplication` Prisma model. Deliberately
 * NOT using BelongsToOrganization — this is the one model in the app that
 * exists precisely because no Organization has been created yet; see the
 * creating migration's comment and MIGRATION_PLAN.md's Stage 8 ADR for the
 * RLS-list confirmation.
 */
#[Fillable([
    'status', 'company_name', 'process_type', 'estimated_annual_spend_eur',
    'current_scheduling_method', 'win_condition', 'contact_name',
    'contact_email', 'contact_phone', 'icp_score', 'reviewer_notes',
    'reviewed_by_admin_user_id', 'reviewed_at',
])]
class PilotApplication extends Model
{
    /** @use HasFactory<PilotApplicationFactory> */
    use HasFactory, HasUlids;

    /** See App\Domain\Organizations\Models\Organization::newFactory()'s docblock. */
    protected static function newFactory(): PilotApplicationFactory
    {
        return PilotApplicationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => PilotApplicationStatus::class,
            'process_type' => ProcessType::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_user_id');
    }

    /**
     * @return HasOne<Organization, $this>
     */
    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class, 'pilot_application_id');
    }
}
