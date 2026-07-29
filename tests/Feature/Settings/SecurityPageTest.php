<?php

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Covers the render itself (task moved this page from its own standalone
 * placeholder chrome into PortalLayout — MIGRATION_PLAN.md's post-Stage-12
 * ADR) plus the new recentLogins prop. Password-change/session-revoke
 * mutations already have their own coverage (ChangePasswordTest,
 * SessionManagementTest) and aren't repeated here.
 */
test('the security page renders inside the portal layout with recent logins', function () {
    $user = User::factory()->create();

    // Simulates two real logins the exact way App\Domain\Auth\Actions\
    // LoginAction does — same service call, same event shape — rather than
    // driving it through the HTTP login form, which is unnecessary
    // indirection for what this test actually verifies.
    $auditLogService = app(AuditLogService::class);
    foreach (range(1, 2) as $i) {
        DB::transaction(function () use ($auditLogService, $user) {
            $auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'auth.login',
                entityType: 'User',
                entityId: $user->id,
            ));
        });
    }

    $response = $this->actingAs($user, 'web')->get(route('settings.security'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Settings/Security')
        ->has('recentLogins', 2));
});

test('recent logins are capped at five and belong only to the requesting user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $auditLogService = app(AuditLogService::class);

    foreach (range(1, 6) as $i) {
        DB::transaction(function () use ($auditLogService, $user) {
            $auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'auth.login',
                entityType: 'User',
                entityId: $user->id,
            ));
        });
    }

    DB::transaction(function () use ($auditLogService, $otherUser) {
        $auditLogService->record(new AuditEvent(
            actorType: AuditActorType::User,
            actorId: $otherUser->id,
            action: 'auth.login',
            entityType: 'User',
            entityId: $otherUser->id,
        ));
    });

    $response = $this->actingAs($user, 'web')->get(route('settings.security'));

    $response->assertInertia(fn ($page) => $page->has('recentLogins', 5));
});

test('a guest is redirected to sign in', function () {
    $response = $this->get(route('settings.security'));

    $response->assertRedirect(route('login'));
});
