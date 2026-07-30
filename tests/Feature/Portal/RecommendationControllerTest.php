<?php

use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Carbon;

function portalRecommendation(Organization $organization): Recommendation
{
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();

    return Recommendation::factory()->for($run, 'optimizationRun')->create();
}

test('accepting a recommendation redirects back with a success status', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $recommendation = portalRecommendation($organization);

    $response = $this->actingAs($user, 'web')->post(route('recommendations.decide', $recommendation), [
        'decision' => 'ACCEPTED',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'decision-recorded');
    expect(RecommendationDecision::query()->where('recommendation_id', $recommendation->id)->exists())->toBeTrue();
});

test('rejecting without a reason redirects back with a decision error', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $recommendation = portalRecommendation($organization);

    $response = $this->actingAs($user, 'web')->post(route('recommendations.decide', $recommendation), [
        'decision' => 'REJECTED',
    ]);

    $response->assertSessionHasErrors('decision');
});

test('a recommendation belonging to another organization redirects back with a not-found error', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $recommendation = portalRecommendation($otherOrganization);
    app(CurrentOrganization::class)->set($organization);

    $response = $this->actingAs($user, 'web')->post(route('recommendations.decide', $recommendation), [
        'decision' => 'ACCEPTED',
    ]);

    $response->assertSessionHasErrors('decision');
});

test('the decision must be one of the known types', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $recommendation = portalRecommendation($organization);

    $response = $this->actingAs($user, 'web')->post(route('recommendations.decide', $recommendation), [
        'decision' => 'MAYBE',
    ]);

    $response->assertSessionHasErrors('decision');
});

/*
 * Same root cause and fix as ScheduleControllerTest's "interpreted as
 * Helsinki local time" test: this adjusted_start_at came from the exact
 * same <input type="datetime-local"> shape, and RecommendationController
 * had the identical unguarded Carbon::parse() bug.
 */
test('a partial-adoption adjusted start time is interpreted as Helsinki local time, not UTC', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $recommendation = portalRecommendation($organization);

    $this->actingAs($user, 'web')->post(route('recommendations.decide', $recommendation), [
        'decision' => 'PARTIAL',
        'adjusted_start_at' => '2024-01-15T09:30',
        'duration_minutes' => 60,
    ]);

    $decision = RecommendationDecision::query()->where('recommendation_id', $recommendation->id)->first();

    expect($decision->adjusted_start_at->equalTo(Carbon::parse('2024-01-15T09:30', 'Europe/Helsinki')))->toBeTrue();
    expect($decision->adjusted_start_at->utc()->format('H:i'))->toBe('07:30');
});

test('a non-member cannot decide a recommendation', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    $recommendation = portalRecommendation($organization);

    $response = $this->post(route('recommendations.decide', $recommendation), [
        'decision' => 'ACCEPTED',
    ]);

    $response->assertRedirect(route('login'));
});
