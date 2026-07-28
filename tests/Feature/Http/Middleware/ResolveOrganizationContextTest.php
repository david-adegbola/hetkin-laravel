<?php

use App\Domain\Organizations\CurrentOrganization;
use App\Models\User;

/**
 * App\Http\Middleware\ResolveOrganizationContext — the Stage 5 consumer of
 * App\Domain\Organizations\Services\OrganizationContextResolver
 * (MIGRATION_PLAN.md ADR-26/ADR-31 anticipated this).
 */
test('a member reaches the portal and their organization is bound and shared', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('organization.id', $organization->id)
        ->where('organization.name', $organization->name));
    expect(app(CurrentOrganization::class)->id())->toBe($organization->id);
});

test('a signed-in user with no organization membership is redirected to sign-in', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('a guest is redirected to sign-in before organization resolution ever runs', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('a member with no membership on the dashboard subdomain is redirected to that subdomain\'s own sign-in', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'web')->get('http://dashboard.localhost/');

    $response->assertRedirect('http://dashboard.localhost/sign-in');
});
