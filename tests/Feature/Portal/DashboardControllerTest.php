<?php

use App\Domain\Alerts\Models\Alert;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;

test('a member sees the portal dashboard', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});

test('the dashboard is also reachable bare on the dashboard subdomain', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get('http://dashboard.localhost/');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Portal/Dashboard'));
});

test('the dashboard lists up to 3 of the organization\'s most recent recommendations', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();
    Recommendation::factory()->for($run, 'optimizationRun')->count(4)->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->has('recentRecommendations', 3));
});

test('the dashboard never shows another organization\'s recommendations', function () {
    ['user' => $user] = portalUser();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $run = OptimizationRun::factory()->for($otherOrganization, 'organization')->create();
    Recommendation::factory()->for($run, 'optimizationRun')->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->has('recentRecommendations', 0));
});

test('a decided recommendation includes its decision', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->create();
    RecommendationDecision::factory()->for($recommendation)->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('recentRecommendations.0.decision.decision', 'ACCEPTED'));
});

test('the dashboard shows the unread alert count and up to 4 recent alerts', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    Alert::factory()->for($organization, 'organization')->count(5)->create();
    Alert::factory()->read()->for($organization, 'organization')->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('activeAlertCount', 5)
        ->has('recentAlerts', 4));
});

test('the dashboard never shows another organization\'s alerts', function () {
    ['user' => $user] = portalUser();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    Alert::factory()->for($otherOrganization, 'organization')->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('activeAlertCount', 0)
        ->has('recentAlerts', 0));
});
