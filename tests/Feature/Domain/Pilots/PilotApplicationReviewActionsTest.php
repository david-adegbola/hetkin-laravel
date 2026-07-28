<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Actions\ApprovePilotApplicationAction;
use App\Domain\Pilots\Actions\RejectPilotApplicationAction;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use App\Notifications\PilotApplicationApproved;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;

test('approving creates an Organization, an OWNER invitation, and marks the application approved', function () {
    Notification::fake();
    $application = PilotApplication::factory()->create([
        'company_name' => 'Acme Cold Storage Oy',
        'contact_email' => 'maria@acme.test',
    ]);
    $admin = AdminUser::factory()->create();

    $result = app(ApprovePilotApplicationAction::class)->execute($application, $admin);

    expect($result->application->status)->toBe(PilotApplicationStatus::Approved);
    expect($result->application->reviewed_by_admin_user_id)->toBe($admin->id);
    expect($result->organization->name)->toBe('Acme Cold Storage Oy');
    expect($result->organization->pilot_application_id)->toBe($application->id);
    expect($result->organization->process_type)->toBe($application->process_type);
    expect($result->invitation->organization_id)->toBe($result->organization->id);
    expect($result->invitation->email)->toBe('maria@acme.test');
    expect($result->invitation->role)->toBe(OrgRole::Owner);
    expect($result->invitation->status)->toBe('pending');
    expect($result->invitation->invited_by_user_id)->toBe("admin:{$admin->id}");
    expect($result->invitation->expires_at->isBetween(now()->addDays(13)->addHours(23), now()->addDays(14)->addMinute()))->toBeTrue();
});

test('the generated slug is url-safe and includes a random suffix', function () {
    Notification::fake();
    $application = PilotApplication::factory()->create(['company_name' => 'Ääkkös & Co!!']);
    $admin = AdminUser::factory()->create();

    $result = app(ApprovePilotApplicationAction::class)->execute($application, $admin);

    expect($result->organization->slug)->toMatch('/^[a-z0-9]+(-[a-z0-9]+)*-[a-z0-9]{5}$/');
});

test('approving an already-approved application throws — no application-level idempotency guard, matching v2', function () {
    // v2 has no explicit "already decided" check either — its backstop is
    // Organization.pilotApplicationId's own @unique constraint, which a
    // second approval violates (an unhandled Prisma error, not a graceful
    // no-op). Same shape here: no early-return in the Action, the
    // database's unique constraint is what actually stops it.
    Notification::fake();
    $application = PilotApplication::factory()->create();
    $admin = AdminUser::factory()->create();

    app(ApprovePilotApplicationAction::class)->execute($application, $admin);

    expect(fn () => app(ApprovePilotApplicationAction::class)->execute($application->refresh(), $admin))
        ->toThrow(QueryException::class);
    expect(Organization::query()->where('pilot_application_id', $application->id)->count())->toBe(1);
});

test('sends the invitation email to the applicant contact', function () {
    Notification::fake();
    $application = PilotApplication::factory()->create(['contact_email' => 'maria@acme.test']);
    $admin = AdminUser::factory()->create();

    app(ApprovePilotApplicationAction::class)->execute($application, $admin);

    Notification::assertSentOnDemand(
        PilotApplicationApproved::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'maria@acme.test',
    );
});

test('writes an audit log entry with organization and invitation ids', function () {
    Notification::fake();
    $application = PilotApplication::factory()->create();
    $admin = AdminUser::factory()->create();

    $result = app(ApprovePilotApplicationAction::class)->execute($application, $admin);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('pilot_application.approved');
    expect($entry->actor_type->value)->toBe('ADMIN_USER');
    expect($entry->organization_id)->toBe($result->organization->id);
    expect($entry->metadata['invitationId'])->toBe($result->invitation->id);
});

test('rejecting marks the application rejected with no organization or invitation created', function () {
    $application = PilotApplication::factory()->create();
    $admin = AdminUser::factory()->create();

    $result = app(RejectPilotApplicationAction::class)->execute($application, $admin);

    expect($result->status)->toBe(PilotApplicationStatus::Rejected);
    expect($result->reviewed_by_admin_user_id)->toBe($admin->id);
    expect(Organization::query()->where('pilot_application_id', $application->id)->exists())->toBeFalse();
    expect(Invitation::withoutGlobalScope(OrganizationScope::class)->count())->toBe(0);
});

test('rejecting writes an audit log entry with no organization', function () {
    $application = PilotApplication::factory()->create();
    $admin = AdminUser::factory()->create();

    app(RejectPilotApplicationAction::class)->execute($application, $admin);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('pilot_application.rejected');
    expect($entry->organization_id)->toBeNull();
});
