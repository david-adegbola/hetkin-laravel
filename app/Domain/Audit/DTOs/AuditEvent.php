<?php

namespace App\Domain\Audit\DTOs;

use App\Domain\Audit\Enums\AuditActorType;

/**
 * Mirrors the Next.js app's `AuditEvent` interface (src/lib/audit.ts)
 * field-for-field — this is exactly what feeds AuditLogService::record()'s
 * hash computation, so its shape is not incidental.
 */
final class AuditEvent
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public readonly AuditActorType $actorType,
        public readonly string $actorId,
        public readonly string $action,
        public readonly string $entityType,
        public readonly string $entityId,
        public readonly ?string $organizationId = null,
        public readonly ?array $metadata = null,
    ) {}
}
