<?php

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;

/**
 * Deliberately lives in tests/Unit, not tests/Feature: RefreshDatabase
 * (applied to the whole 'Feature' suite in tests/Pest.php) wraps every test
 * in its own outer DB::transaction() for fast rollback-based isolation,
 * which makes DB::transactionLevel() === 0 unreachable from a 'Feature'
 * test — exactly the condition this test needs to exercise.
 */
test('record() refuses to run outside an active transaction', function () {
    $service = app(AuditLogService::class);

    $event = new AuditEvent(
        actorType: AuditActorType::AdminUser,
        actorId: 'admin-1',
        action: 'organization.note.created',
        entityType: 'Organization',
        entityId: 'org-1',
    );

    expect(fn () => $service->record($event))
        ->toThrow(LogicException::class, 'must be called inside an existing DB::transaction()');
});
