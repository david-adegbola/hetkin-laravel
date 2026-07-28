<?php

namespace App\Domain\Organizations\Models;

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Enums\ProcessType;
use App\Domain\Pilots\Models\PilotApplication;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirrors the Next.js app's `Organization` Prisma model — the tenant root
 * every scoped domain model (Member/Invitation here; Site,
 * ProductionSchedule, etc. from Stage 4 onward) belongs to. This model
 * itself is NOT scoped by App\Domain\Organizations\Concerns\
 * BelongsToOrganization — it IS the tenant, not a tenant-owned row.
 */
#[Fillable([
    'name', 'slug', 'logo', 'status', 'base_fee_cents',
    'revenue_share_percent', 'stripe_customer_id', 'process_type',
    'pilot_application_id',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids;

    /**
     * HasFactory's default resolver guesses a factory namespace by
     * stripping the `App\Models\` prefix — models outside that namespace
     * (everything under App\Domain\*) resolve to the wrong FQCN otherwise,
     * so every Domain model factory is wired explicitly instead of relying
     * on the guess. Same reasoning on Member/Invitation.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'process_type' => ProcessType::class,
            'revenue_share_percent' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<AuditLogEntry, $this>
     */
    public function auditLogEntries(): HasMany
    {
        return $this->hasMany(AuditLogEntry::class);
    }

    /**
     * @return BelongsTo<PilotApplication, $this>
     */
    public function pilotApplication(): BelongsTo
    {
        return $this->belongsTo(PilotApplication::class);
    }
}
