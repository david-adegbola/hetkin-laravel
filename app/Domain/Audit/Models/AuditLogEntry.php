<?php

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only, hash-chained. Only App\Domain\Audit\Services\AuditLogService
 * is permitted to create rows here — mirrors the Next.js app's
 * src/lib/audit.ts, "the only module permitted to write to
 * `audit_log_entry`." Update and delete are blocked at the model level
 * (not just by convention) so tampering with the chain requires bypassing
 * Eloquent entirely, which is itself detectable via verifyAuditChainIntegrity-
 * style checks (App\Domain\Audit\Services\AuditChainIntegrityChecker).
 *
 * No `updated_at` — see the migration's comment.
 */
class AuditLogEntry extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'actor_type',
        'actor_id',
        'action',
        'entity_type',
        'entity_id',
        'metadata',
        'previous_hash',
        'hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'metadata' => 'array',
            // Plain 'datetime', deliberately NOT 'datetime:<format>': a
            // custom-format cast string only controls how the attribute
            // serializes in PHP/array/JSON output — Eloquent's setAttribute()
            // only routes a value through fromDateTime() (which is what
            // actually applies getDateFormat() below, for the SQL INSERT)
            // for the plain 'datetime'/'date' cast types. A 'datetime:...'
            // cast silently skips that conversion, so the value that ends
            // up in the DB comes from Carbon's own default __toString()
            // format instead — whole seconds, not microseconds — no matter
            // what format string you write here. Learned the hard way; see
            // the AuditLogServiceTest failures this fixed.
            'created_at' => 'datetime',
        ];
    }

    /**
     * Model-wide storage-format precision for 'datetime'-cast attributes on
     * INSERT/UPDATE. Must match the migration's `timestamp(..., precision:
     * 6)` column, or the hash chain's integrity check will fail for any two
     * entries written within the same second (the value used to compute the
     * hash at write time would differ from what's actually persisted and
     * read back at verify time).
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    /**
     * Plain belongsTo, deliberately NOT using
     * App\Domain\Organizations\Concerns\BelongsToOrganization — this table
     * is intentionally cross-tenant (see App\Domain\Audit\Services\
     * AuditLogService's docblock on why the hash chain must read globally,
     * not per-organization), so it must never gain the fail-closed
     * OrganizationScope that trait applies.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('AuditLogEntry rows are append-only and can never be updated.');
        });

        static::deleting(function () {
            throw new LogicException('AuditLogEntry rows are append-only and can never be deleted.');
        });
    }
}
