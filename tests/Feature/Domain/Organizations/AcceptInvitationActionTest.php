<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Organizations\Actions\AcceptInvitationAction;
use App\Domain\Organizations\Exceptions\InvitationException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\User;

test('accepts a pending invitation and creates a member with the invited role', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);

    app(AcceptInvitationAction::class)->execute($invitation, $user);

    expect(Member::isMemberOf($user, $invitation->organization_id))->toBeTrue();
    $member = Member::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $invitation->organization_id)
        ->where('user_id', $user->id)
        ->first();
    expect($member->role)->toBe($invitation->role);
    expect($invitation->refresh()->status)->toBe('accepted');
});

test('throws for an already-accepted invitation', function () {
    $invitation = Invitation::factory()->accepted()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);

    expect(fn () => app(AcceptInvitationAction::class)->execute($invitation, $user))
        ->toThrow(InvitationException::class, 'already been used');
});

test('throws for an expired invitation', function () {
    $invitation = Invitation::factory()->expired()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);

    expect(fn () => app(AcceptInvitationAction::class)->execute($invitation, $user))
        ->toThrow(InvitationException::class, 'expired');
});

test('throws when the signed-in email does not match the invitation email', function () {
    $invitation = Invitation::factory()->create(['email' => 'invited@example.test']);
    $user = User::factory()->create(['email' => 'someone-else@example.test']);

    expect(fn () => app(AcceptInvitationAction::class)->execute($invitation, $user))
        ->toThrow(InvitationException::class, 'different email address');
});

test('the email match is case-insensitive', function () {
    $invitation = Invitation::factory()->create(['email' => 'Person@Example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);

    app(AcceptInvitationAction::class)->execute($invitation, $user);

    expect($invitation->refresh()->status)->toBe('accepted');
});

test('is a no-op when the user is already a member', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);
    Member::factory()->for($invitation->organization, 'organization')->create(['user_id' => $user->id]);

    app(AcceptInvitationAction::class)->execute($invitation, $user);

    expect(Member::withoutGlobalScope(OrganizationScope::class)
        ->where('organization_id', $invitation->organization_id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
    // Invitation status is untouched by the no-op path, matching v2.
    expect($invitation->refresh()->status)->toBe('pending');
});

test('writes an audit log entry attributed to the accepting user', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);

    app(AcceptInvitationAction::class)->execute($invitation, $user);

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('invitation.accepted');
    expect($entry->actor_type->value)->toBe('USER');
    expect($entry->actor_id)->toBe($user->id);
    expect($entry->organization_id)->toBe($invitation->organization_id);
});
