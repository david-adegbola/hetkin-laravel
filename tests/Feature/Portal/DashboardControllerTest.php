<?php

use App\Domain\Alerts\Models\Alert;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Support\Carbon;

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

test('a recommendation includes its real price curve and savings calculation inputs', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    $start = Carbon::parse('2026-08-10 08:00:00', 'UTC');
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'asset_label' => 'Batch line 3',
        'start_at' => $start,
        'end_at' => $start->copy()->addHours(2),
        'estimated_kwh' => 400,
    ]);
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create([
        'proposed_start_at' => $start->copy()->setHour(2),
        'proposed_end_at' => $start->copy()->setHour(4),
        'projected_savings_cents' => 1_240,
    ]);

    foreach (range(0, 23) as $hour) {
        ElectricityPrice::factory()->create([
            'hour_start' => $start->copy()->startOfDay()->addHours($hour),
            'eur_per_mwh' => $hour < 4 ? 50 : 100,
        ]);
    }

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('recentRecommendations.0.asset_label', 'Batch line 3')
        ->where('recentRecommendations.0.estimated_kwh', 400)
        ->where('recentRecommendations.0.original_average_eur_per_mwh', 100)
        ->where('recentRecommendations.0.proposed_average_eur_per_mwh', 50)
        ->has('recentRecommendations.0.price_curve', 24));
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

test('verified savings MTD only sums live-approved records within the current month', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'period_start' => now()->startOfMonth(),
        'verified_savings_cents' => 15_000,
    ]);
    // Same month, but not yet approved — must not count.
    VerificationRecord::factory()->for($organization, 'organization')->create([
        'period_start' => now()->startOfMonth(),
        'verified_savings_cents' => 99_999,
    ]);
    // Approved, but last month — must not count toward MTD.
    VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'period_start' => now()->subMonthNoOverflow()->startOfMonth(),
        'verified_savings_cents' => 50_000,
    ]);

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('mtdSavingsCents', 15_000));
});

test('verified savings YTD sums every live-approved record in the current year', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'period_start' => now()->startOfMonth(),
        'verified_savings_cents' => 10_000,
    ]);
    VerificationRecord::factory()->liveApproved()->for($organization, 'organization')->create([
        'period_start' => now()->startOfYear(),
        'verified_savings_cents' => 20_000,
    ]);

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->where('ytdSavingsCents', 30_000));
});

test('recommendation adoption counts accepted and partial decisions as followed', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();

    $accepted = Recommendation::factory()->for($run, 'optimizationRun')->create();
    RecommendationDecision::factory()->for($accepted)->create(['decision' => RecommendationDecisionType::Accepted]);
    $partial = Recommendation::factory()->for($run, 'optimizationRun')->create();
    RecommendationDecision::factory()->for($partial)->create(['decision' => RecommendationDecisionType::Partial]);
    $rejected = Recommendation::factory()->for($run, 'optimizationRun')->create();
    RecommendationDecision::factory()->for($rejected)->create(['decision' => RecommendationDecisionType::Rejected]);

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('adoptionRate', 67)
        ->where('followedCount', 2)
        ->where('totalDecisions', 3));
});

test('adoption rate is null when no recommendations have been decided yet', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('adoptionRate', null)
        ->where('totalDecisions', 0));
});

test('the onboarding checklist reflects real organization state', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);

    $site = Site::factory()->for($organization, 'organization')->create();
    $meteringPoint = MeteringPoint::factory()->for($site)->create();
    MeterReading::factory()->for($meteringPoint, 'meteringPoint')->create();
    SavingsAssessment::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasMeterData', true)
        ->where('onboarding.hasAssessment', true)
        ->where('onboarding.hasScheduleBlock', true)
        ->where('onboarding.hasDecision', false));
});

test('the onboarding checklist is all-false for a brand new organization', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('onboarding.hasMeterData', false)
        ->where('onboarding.hasAssessment', false)
        ->where('onboarding.hasScheduleBlock', false)
        ->where('onboarding.hasDecision', false));
});
