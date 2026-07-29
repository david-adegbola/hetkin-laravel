<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Enums\OrgRole;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Models\User;

test('the team page lists members and pending invitations for the organization', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $teammate = User::factory()->create(['name' => 'Alex Teammate']);
    Member::factory()->for($teammate)->for($organization, 'organization')
        ->role(OrgRole::Scheduler)->create();

    Invitation::factory()->for($organization, 'organization')->create([
        'email' => 'invitee@example.com',
        'role' => OrgRole::Viewer,
    ]);

    $response = $this->actingAs($user, 'web')->get(route('team'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/Team')
        ->has('members', 2)
        ->has('invitations', 1)
        ->where('invitations.0.email', 'invitee@example.com')
        ->where('invitations.0.role', 'VIEWER'));
});

test('accepted invitations are not listed as pending', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    Invitation::factory()->accepted()->for($organization, 'organization')->create();

    $response = $this->actingAs($user, 'web')->get(route('team'));

    $response->assertInertia(fn ($page) => $page->has('invitations', 0));
});

test('a non-member cannot reach the team page', function () {
    $response = $this->get(route('team'));

    $response->assertRedirect(route('login'));
});
