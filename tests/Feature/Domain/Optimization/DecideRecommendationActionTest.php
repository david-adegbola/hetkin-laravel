<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Optimization\Actions\DecideRecommendationAction;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Exceptions\OptimizationActionException;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;

function recommendationFor(Organization $organization): Recommendation
{
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();

    return Recommendation::factory()->for($run, 'optimizationRun')->create();
}

test('accepts a recommendation with no reason required', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    $decision = app(DecideRecommendationAction::class)->execute(
        $organization,
        $user,
        $recommendation->id,
        RecommendationDecisionType::Accepted,
        null,
        null,
        null,
    );

    expect($decision)->toBeInstanceOf(RecommendationDecision::class);
    expect($decision->decision)->toBe(RecommendationDecisionType::Accepted);
    expect($decision->decided_by_id)->toBe($user->id);
});

test('rejecting without a reason throws', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    expect(fn () => app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Rejected, null, null, null,
    ))->toThrow(OptimizationActionException::class, 'A reason is required to reject a recommendation.');
});

test('rejecting with a reason succeeds', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    $decision = app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Rejected,
        'Conflicts with a delivery commitment.', null, null,
    );

    expect($decision->decision)->toBe(RecommendationDecisionType::Rejected);
    expect($decision->reason)->toBe('Conflicts with a delivery commitment.');
});

test('a partial decision without an adjusted start time throws', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    expect(fn () => app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Partial, null, null, 60,
    ))->toThrow(OptimizationActionException::class, 'Enter the time you\'re actually planning to move this to.');
});

test('a partial decision without a duration throws', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    expect(fn () => app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Partial,
        null, Carbon::parse('2026-01-01 10:00'), null,
    ))->toThrow(OptimizationActionException::class, 'Could not determine the block\'s duration');
});

test('a well-formed partial decision computes the adjusted end time from the duration', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    $decision = app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Partial,
        null, Carbon::parse('2026-01-01 10:00', 'UTC'), 90,
    );

    expect($decision->adjusted_start_at->toDateTimeString())->toBe('2026-01-01 10:00:00');
    expect($decision->adjusted_end_at->toDateTimeString())->toBe('2026-01-01 11:30:00');
});

test('a recommendation belonging to another organization is not found', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor(Organization::factory()->create());

    expect(fn () => app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Accepted, null, null, null,
    ))->toThrow(OptimizationActionException::class, 'Recommendation not found.');
});

test('a recommendation that already has a decision cannot be decided again', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);
    RecommendationDecision::factory()->for($recommendation)->create();

    expect(fn () => app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Accepted, null, null, null,
    ))->toThrow(OptimizationActionException::class, 'This recommendation already has a decision recorded.');
});

test('writes an audit log entry matching the decision type', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $recommendation = recommendationFor($organization);

    $decision = app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Accepted, null, null, null,
    );

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('recommendation.accepted');
    expect($entry->organization_id)->toBe($organization->id);
    expect($entry->entity_id)->toBe($decision->id);
});
