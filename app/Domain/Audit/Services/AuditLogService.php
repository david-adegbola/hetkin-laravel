<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Models\AuditLogEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The only class permitted to write to `audit_log_entries` — mirrors the
 * Next.js app's src/lib/audit.ts::writeAuditLog(). The log is hash-chained:
 * each entry's hash covers its own content plus the previous entry's hash,
 * so a row edited or deleted out of band breaks the chain and is
 * detectable, not just discouraged by convention (also enforced at the
 * model level — see AuditLogEntry::booted()).
 *
 * The chain is GLOBAL across every organization, not per-tenant — matching
 * the original's design (see the Next.js app's src/lib/db-rls.ts for why:
 * an org-scoped read would make more-recent entries from other orgs
 * invisible and silently fork the chain). Every domain Action that needs to
 * write an audit entry should call record() inside the SAME
 * DB::transaction() as the domain write it documents — enforced here, not
 * just documented: record() throws if called outside an active transaction.
 *
 * Known limitation, inherited from the original design (not introduced
 * here): the very first row ever written has no previous row to
 * `lockForUpdate()`, so two concurrent transactions both writing the
 * chain's genesis entry could theoretically both claim GENESIS_HASH as
 * their previousHash. This only matters once, at the app's very first
 * audit event, and every write after that is fully serialized by the
 * `lockForUpdate()` below.
 */
class AuditLogService
{
    public function record(AuditEvent $event): AuditLogEntry
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'AuditLogService::record() must be called inside an existing DB::transaction(), '.
                'so the audit entry can never diverge from the domain write it documents.',
            );
        }

        // Tiebreak on 'id' (ULID, itself time-sortable): two entries created
        // within the same timestamp-column tick would otherwise make
        // "the last entry" ambiguous.
        /** @var AuditLogEntry|null $last */
        $last = AuditLogEntry::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $previousHash = $last?->hash ?? self::genesisHash();
        $createdAt = Carbon::now();
        $hash = $this->computeHash($previousHash, $event, $createdAt);

        return AuditLogEntry::create([
            'organization_id' => $event->organizationId,
            'actor_type' => $event->actorType,
            'actor_id' => $event->actorId,
            'action' => $event->action,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'metadata' => $event->metadata,
            'previous_hash' => $previousHash,
            'hash' => $hash,
            'created_at' => $createdAt,
        ]);
    }

    public function computeHash(string $previousHash, AuditEvent $event, Carbon $createdAt): string
    {
        $payload = json_encode([
            'previousHash' => $previousHash,
            'organizationId' => $event->organizationId,
            'actorType' => $event->actorType->value,
            'actorId' => $event->actorId,
            'action' => $event->action,
            'entityType' => $event->entityType,
            'entityId' => $event->entityId,
            'metadata' => $event->metadata,
            // Carbon's toISOString() already includes microseconds by
            // default — that precision has to match what's actually
            // persisted and read back (see AuditLogEntry's cast and the
            // migration's column precision), or this recomputes differently
            // at verify time than it did at write time for any two entries
            // landing in the same second.
            'createdAt' => $createdAt->toISOString(),
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    public static function genesisHash(): string
    {
        return str_repeat('0', 64);
    }
}
