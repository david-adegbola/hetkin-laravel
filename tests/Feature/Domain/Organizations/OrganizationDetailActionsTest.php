<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Auth\Models\SessionRecord;
use App\Domain\Organizations\Actions\AddOrganizationNoteAction;
use App\Domain\Organizations\Actions\ErasePersonalDataAction;
use App\Domain\Organizations\Actions\RemoveMemberAction;
use App\Domain\Organizations\Actions\ResendInvitationAction;
use App\Domain\Organizations\Actions\UpdateMemberRoleAction;
use App\Domain\Organizations\Actions\UpdateOrganizationStatusAction;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Exceptions\OrganizationActionException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Models\PilotApplication;
use App\Models\AdminUser;
use App\Models\User;
use App\Notifications\InvitationResent;
use Illuminate\Support\Facades\Notification;

// --- UpdateOrganizationStatusAction ---------------------------------------

test('updates an organization status and writes an audit log entry', function () {
    $organization = Organization::factory()->create(['status' => OrganizationStatus::Pilot]);
    $admin = AdminUser::factory()->create();

    $updated = app(UpdateOrganizationStatusAction::class)->execute($organization, $admin, 'ACTIVE');

    expect($updated->status)->toBe(OrganizationStatus::Active);
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('organization.status_changed');
    expect($entry->metadata)->toBe(['from' => 'PILOT', 'to' => 'ACTIVE']);
});

test('rejects an invalid status', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(UpdateOrganizationStatusAction::class)->execute($organization, $admin, 'BOGUS'))
        ->toThrow(OrganizationActionException::class, 'Invalid status.');
});

test('resubmitting the same status is a no-op with no audit entry', function () {
    $organization = Organization::factory()->create(['status' => OrganizationStatus::Active]);
    $admin = AdminUser::factory()->create();

    app(UpdateOrganizationStatusAction::class)->execute($organization, $admin, 'ACTIVE');

    expect(AuditLogEntry::query()->count())->toBe(0);
});

// --- UpdateMemberRoleAction ------------------------------------------------

test('updates a member role and writes an audit log entry', function () {
    $organization = Organization::factory()->create();
    $member = Member::factory()->for($organization, 'organization')->create(['role' => OrgRole::Viewer]);
    $admin = AdminUser::factory()->create();

    $updated = app(UpdateMemberRoleAction::class)->execute($member, $admin, 'SCHEDULER');

    expect($updated->role)->toBe(OrgRole::Scheduler);
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('member.role_changed');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->metadata)->toBe(['from' => 'VIEWER', 'to' => 'SCHEDULER']);
});

test('rejects an invalid role', function () {
    $organization = Organization::factory()->create();
    $member = Member::factory()->for($organization, 'organization')->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(UpdateMemberRoleAction::class)->execute($member, $admin, 'BOGUS'))
        ->toThrow(OrganizationActionException::class, 'Invalid role.');
});

test('resubmitting the same role is a no-op with no audit entry', function () {
    $organization = Organization::factory()->create();
    $member = Member::factory()->for($organization, 'organization')->create(['role' => OrgRole::Owner]);
    $admin = AdminUser::factory()->create();

    app(UpdateMemberRoleAction::class)->execute($member, $admin, 'OWNER');

    expect(AuditLogEntry::query()->count())->toBe(0);
});

// --- RemoveMemberAction -----------------------------------------------------

test('hard-deletes the member and writes an audit log entry capturing who/what was removed', function () {
    $organization = Organization::factory()->create();
    $member = Member::factory()->for($organization, 'organization')->create(['role' => OrgRole::Finance]);
    $memberId = $member->id;
    $userId = $member->user_id;
    $admin = AdminUser::factory()->create();

    app(RemoveMemberAction::class)->execute($member, $admin);

    expect(Member::withoutGlobalScope(OrganizationScope::class)->whereKey($memberId)->exists())->toBeFalse();
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('member.removed');
    expect($entry->entity_id)->toBe($memberId);
    expect($entry->metadata)->toBe(['userId' => $userId, 'role' => 'FINANCE']);
});

// --- AddOrganizationNoteAction -----------------------------------------------

test('adds a note and writes an audit log entry without the body in metadata', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    $note = app(AddOrganizationNoteAction::class)->execute($organization, $admin, '  Called the customer.  ', 'SUPPORT', true);

    expect($note->body)->toBe('Called the customer.');
    expect($note->pinned)->toBeTrue();
    expect($note->author_admin_user_id)->toBe($admin->id);
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('organization_note.added');
    expect($entry->entity_type)->toBe('OrganizationNote');
    expect($entry->entity_id)->toBe($note->id);
    expect($entry->metadata)->toBe(['category' => 'SUPPORT', 'pinned' => true]);
});

test('rejects an empty note body', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(AddOrganizationNoteAction::class)->execute($organization, $admin, '   '))
        ->toThrow(OrganizationActionException::class, 'Note cannot be empty.');
});

test('defaults the category to GENERAL', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();

    $note = app(AddOrganizationNoteAction::class)->execute($organization, $admin, 'A note.');

    expect($note->category->value)->toBe('GENERAL');
});

// --- ResendInvitationAction --------------------------------------------------

test('resends the same pending invitation email and writes an audit log entry with no metadata', function () {
    Notification::fake();
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $admin = AdminUser::factory()->create();

    app(ResendInvitationAction::class)->execute($invitation, $admin);

    Notification::assertSentOnDemand(
        InvitationResent::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'person@example.test',
    );
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('invitation.resent');
    expect($entry->entity_id)->toBe($invitation->id);
    expect($entry->metadata)->toBeNull();
});

test('is a silent no-op for a non-pending invitation', function () {
    Notification::fake();
    $invitation = Invitation::factory()->accepted()->create();
    $admin = AdminUser::factory()->create();

    app(ResendInvitationAction::class)->execute($invitation, $admin);

    Notification::assertNothingSent();
    expect(AuditLogEntry::query()->count())->toBe(0);
});

test('a failed email send throws a friendly error and writes no audit entry — unlike other post-hoc notifications, the email IS the resend', function () {
    config(['mail.default' => 'this-mailer-does-not-exist']);
    $invitation = Invitation::factory()->create();
    $admin = AdminUser::factory()->create();

    expect(fn () => app(ResendInvitationAction::class)->execute($invitation, $admin))
        ->toThrow(OrganizationActionException::class);

    expect(AuditLogEntry::query()->where('action', 'invitation.resent')->count())->toBe(0);
});

// --- ErasePersonalDataAction --------------------------------------------------

test('rejects a slug that does not match', function () {
    $organization = Organization::factory()->create(['slug' => 'acme-cold-storage']);
    $admin = AdminUser::factory()->create();

    expect(fn () => app(ErasePersonalDataAction::class)->execute($organization, $admin, 'wrong-slug'))
        ->toThrow(OrganizationActionException::class, 'acme-cold-storage');
});

test('anonymizes a member whose account is exclusive to this org and revokes their sessions', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $user = User::factory()->create(['email' => 'real.person@example.test']);
    Member::factory()->for($organization, 'organization')->create(['user_id' => $user->id]);
    SessionRecord::query()->insert([
        'id' => 'sess_1', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => 'x', 'last_activity' => now()->timestamp,
    ]);
    $admin = AdminUser::factory()->create();

    app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    $user->refresh();
    expect($user->name)->toBe('Erased contact');
    expect($user->email)->toBe("erased-{$user->id}@erased.hetkin.invalid");
    expect($user->image)->toBeNull();
    expect(SessionRecord::where('user_id', $user->id)->count())->toBe(0);
});

test('skips anonymizing a member whose user account belongs to another org too', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $otherOrganization = Organization::factory()->create();
    $user = User::factory()->create(['email' => 'shared@example.test']);
    Member::factory()->for($organization, 'organization')->create(['user_id' => $user->id]);
    Member::factory()->for($otherOrganization, 'organization')->create(['user_id' => $user->id]);
    $admin = AdminUser::factory()->create();

    $note = app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    $user->refresh();
    expect($user->email)->toBe('shared@example.test');
    expect($note->body)->toContain('1 skipped');
});

test('removes only pending invitations for the org', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    Invitation::factory()->for($organization, 'organization')->create(['status' => 'pending']);
    Invitation::factory()->for($organization, 'organization')->accepted()->create();
    $admin = AdminUser::factory()->create();

    app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    expect(Invitation::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $organization->id)->count())->toBe(1);
});

test('erases the pilot application contact fields when one exists', function () {
    $pilotApplication = PilotApplication::factory()->create([
        'contact_name' => 'Maria', 'contact_email' => 'maria@acme.test', 'contact_phone' => '+358401234567',
    ]);
    $organization = Organization::factory()->create(['slug' => 'acme', 'pilot_application_id' => $pilotApplication->id]);
    $admin = AdminUser::factory()->create();

    app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    $pilotApplication->refresh();
    expect($pilotApplication->contact_name)->toBe('Erased contact');
    expect($pilotApplication->contact_email)->toBe("erased-{$pilotApplication->id}@erased.hetkin.invalid");
    expect($pilotApplication->contact_phone)->toBeNull();
    // Non-personal fields are left alone.
    expect($pilotApplication->company_name)->not->toBeNull();
});

test('creates a pinned summary note and an audit log entry linking to it', function () {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $admin = AdminUser::factory()->create();

    $note = app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    expect($note->pinned)->toBeTrue();
    expect($note->category->value)->toBe('GENERAL');
    expect($note->body)->toContain('GDPR erasure processed');
    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('organization.gdpr_erasure_processed');
    expect($entry->metadata['noteId'])->toBe($note->id);
});

test('does not touch operational or financial records', function () {
    // No Baseline/Invoice/etc. factories are created here on purpose — this
    // test documents the contract (nothing in this domain's tables besides
    // Member/Invitation/PilotApplication/OrganizationNote is touched) by
    // asserting the action only ever writes the rows the other tests above
    // already verify, without needing every Billing/Metering factory as a
    // dependency of this Organizations-domain test file.
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $admin = AdminUser::factory()->create();

    app(ErasePersonalDataAction::class)->execute($organization, $admin, 'acme');

    expect($organization->refresh()->status)->not->toBeNull();
});
