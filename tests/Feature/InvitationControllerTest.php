<?php

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Models\User;

test('the invite page shows the organization name for a valid invitation', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);

    $response = $this->get(route('invite.show', ['invitation' => $invitation->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Invite/Accept')
        ->where('invitation.email', 'person@example.test')
        ->where('invalid_reason', null));
});

test('an unknown invitation id shows an invalid reason', function () {
    $response = $this->get(route('invite.show', ['invitation' => 'nonexistent']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('invitation', null)
        ->where('invalid_reason', "This invitation link isn't valid."));
});

test('an expired invitation shows an invalid reason', function () {
    $invitation = Invitation::factory()->expired()->create();

    $response = $this->get(route('invite.show', ['invitation' => $invitation->id]));

    $response->assertInertia(fn ($page) => $page->where('invalid_reason', 'This invitation has expired. Ask your Hetkin contact to resend it.'));
});

test('accepting creates a new account, logs them in, and creates a member', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);

    $response = $this->post(route('invite.accept', ['invitation' => $invitation->id]), [
        'name' => 'Person One',
        'password' => 'a-Very-Str0ng-Passw0rd!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated('web');
    $user = User::where('email', 'person@example.test')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Person One');
    expect(Member::isMemberOf($user, $invitation->organization_id))->toBeTrue();
    expect($invitation->refresh()->status)->toBe('accepted');
});

test('accepting with an existing account email falls back to signing in', function () {
    $existingUser = User::factory()->create([
        'email' => 'person@example.test',
        'password' => bcrypt('correct-Horse-Battery-Staple1!'),
    ]);
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);

    $response = $this->post(route('invite.accept', ['invitation' => $invitation->id]), [
        'name' => 'Ignored',
        'password' => 'correct-Horse-Battery-Staple1!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($existingUser, 'web');
    expect(User::query()->where('email', 'person@example.test')->count())->toBe(1);
});

test('a wrong password for an existing account redirects back with an error', function () {
    User::factory()->create([
        'email' => 'person@example.test',
        'password' => bcrypt('correct-Horse-Battery-Staple1!'),
    ]);
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);

    $response = $this->post(route('invite.accept', ['invitation' => $invitation->id]), [
        'name' => 'Ignored',
        'password' => 'totally-Wrong-Password1!',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest('web');
});

test('accepting an already-used invitation redirects back with an error', function () {
    $invitation = Invitation::factory()->accepted()->create(['email' => 'person@example.test']);

    $response = $this->post(route('invite.accept', ['invitation' => $invitation->id]), [
        'name' => 'Person One',
        'password' => 'a-Very-Str0ng-Passw0rd!',
    ]);

    $response->assertSessionHasErrors('password');
});

test('accepting a nonexistent invitation 404s', function () {
    $response = $this->post(route('invite.accept', ['invitation' => 'nonexistent']), [
        'name' => 'Person One',
        'password' => 'a-Very-Str0ng-Passw0rd!',
    ]);

    $response->assertNotFound();
});
