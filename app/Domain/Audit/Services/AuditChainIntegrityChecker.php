<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Models\AuditLogEntry;

/**
 * Walks the full chain and confirms every entry's hash matches its
 * recomputed value — mirrors the Next.js app's
 * src/lib/audit.ts::verifyAuditChainIntegrity(). Intended for the admin
 * audit-log view (Stage 9) and periodic automated integrity checks, not the
 * hot path — this is an O(n) full-table scan.
 */
class AuditChainIntegrityChecker
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /**
     * @return array{valid: bool, brokenAtEntryId: ?string}
     */
    public function verify(): array
    {
        $previousHash = AuditLogService::genesisHash();

        foreach (AuditLogEntry::query()->orderBy('created_at')->orderBy('id')->cursor() as $entry) {
            $expectedHash = $this->auditLogService->computeHash(
                $previousHash,
                new AuditEvent(
                    actorType: $entry->actor_type,
                    actorId: $entry->actor_id,
                    action: $entry->action,
                    entityType: $entry->entity_type,
                    entityId: $entry->entity_id,
                    organizationId: $entry->organization_id,
                    metadata: $entry->metadata,
                ),
                $entry->created_at,
            );

            if ($entry->previous_hash !== $previousHash || $entry->hash !== $expectedHash) {
                return ['valid' => false, 'brokenAtEntryId' => $entry->id];
            }

            $previousHash = $entry->hash;
        }

        return ['valid' => true, 'brokenAtEntryId' => null];
    }
}
