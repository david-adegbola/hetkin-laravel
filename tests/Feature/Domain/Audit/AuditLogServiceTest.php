<?php

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Audit\Services\AuditChainIntegrityChecker;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * organizationId defaults to null, not a placeholder string: since Stage 3
 * added a real foreign key from audit_log_entries.organization_id to
 * organizations.id (MIGRATION_PLAN.md ADR-25), any non-null value here has
 * to reference a real row. Nothing in this file is testing organization
 * scoping except the one test that says so in its name — that test creates
 * real Organization rows instead of using this default.
 */
function makeAuditEvent(array $overrides = []): AuditEvent
{
    return new AuditEvent(
        actorType: $overrides['actorType'] ?? AuditActorType::AdminUser,
        actorId: $overrides['actorId'] ?? 'admin-1',
        action: $overrides['action'] ?? 'organization.note.created',
        entityType: $overrides['entityType'] ?? 'Organization',
        entityId: $overrides['entityId'] ?? 'org-1',
        organizationId: $overrides['organizationId'] ?? null,
        metadata: $overrides['metadata'] ?? null,
    );
}

test('the first entry chains from the genesis hash', function () {
    $service = app(AuditLogService::class);

    $entry = DB::transaction(fn () => $service->record(makeAuditEvent()));

    expect($entry->previous_hash)->toBe(AuditLogService::genesisHash());
    expect($entry->hash)->not->toBe(AuditLogService::genesisHash());
    expect($entry->hash)->toHaveLength(64);
});

test('each subsequent entry chains from the previous entry\'s hash', function () {
    $service = app(AuditLogService::class);

    $first = DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'first'])));
    $second = DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'second'])));

    expect($second->previous_hash)->toBe($first->hash);
});

test('the chain is global across organizations, not per-tenant', function () {
    $service = app(AuditLogService::class);
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $entryA = DB::transaction(fn () => $service->record(makeAuditEvent(['organizationId' => $orgA->id])));
    $entryB = DB::transaction(fn () => $service->record(makeAuditEvent(['organizationId' => $orgB->id])));

    expect($entryB->previous_hash)->toBe($entryA->hash);
});

test('AuditLogEntry rows cannot be updated', function () {
    $service = app(AuditLogService::class);
    $entry = DB::transaction(fn () => $service->record(makeAuditEvent()));

    expect(fn () => $entry->update(['action' => 'tampered']))
        ->toThrow(LogicException::class, 'append-only');
});

test('AuditLogEntry rows cannot be deleted', function () {
    $service = app(AuditLogService::class);
    $entry = DB::transaction(fn () => $service->record(makeAuditEvent()));

    expect(fn () => $entry->delete())
        ->toThrow(LogicException::class, 'append-only');
});

test('the integrity checker confirms a valid chain', function () {
    $service = app(AuditLogService::class);
    DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'first'])));
    DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'second'])));
    DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'third'])));

    $result = app(AuditChainIntegrityChecker::class)->verify();

    expect($result['valid'])->toBeTrue();
    expect($result['brokenAtEntryId'])->toBeNull();
});

test('the integrity checker detects a tampered entry', function () {
    $service = app(AuditLogService::class);
    DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'first'])));
    $second = DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'second'])));
    DB::transaction(fn () => $service->record(makeAuditEvent(['action' => 'third'])));

    // Bypass the model guard to simulate exactly the out-of-band tampering
    // the hash chain exists to detect (a direct UPDATE, not through Eloquent).
    DB::table('audit_log_entries')->where('id', $second->id)->update(['action' => 'tampered']);

    $result = app(AuditChainIntegrityChecker::class)->verify();

    expect($result['valid'])->toBeFalse();
    expect($result['brokenAtEntryId'])->toBe($second->id);
});

test('metadata round-trips through the hash without affecting unrelated entries', function () {
    $service = app(AuditLogService::class);

    $entry = DB::transaction(fn () => $service->record(makeAuditEvent([
        'metadata' => ['before' => 'DRAFT', 'after' => 'LOCKED'],
    ])));

    expect(AuditLogEntry::find($entry->id)->metadata)->toBe(['before' => 'DRAFT', 'after' => 'LOCKED']);
    expect(app(AuditChainIntegrityChecker::class)->verify()['valid'])->toBeTrue();
});
