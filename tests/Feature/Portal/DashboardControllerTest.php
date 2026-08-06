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
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $start = Carbon::now(config('scheduling.timezone'))->startOfDay()->utc();
    foreach (range(0, 3) as $hour) {
        $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
            'start_at' => $start->copy()->addHours($hour),
            'end_at' => $start->copy()->addHours($hour + 1),
        ]);
        Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create();
    }

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->has('todayRecommendations', 3));
});

test('the dashboard never shows another organization\'s recommendations', function () {
    ['user' => $user] = portalUser();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $run = OptimizationRun::factory()->for($otherOrganization, 'organization')->create();
    Recommendation::factory()->for($run, 'optimizationRun')->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page->has('todayRecommendations', 0));
});

test('a decided recommendation includes its decision', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $start = Carbon::now(config('scheduling.timezone'))->startOfDay()->utc();
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
        'start_at' => $start,
        'end_at' => $start->copy()->addHour(),
    ]);
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create();
    RecommendationDecision::factory()->for($recommendation)->create();

    $response = $this->actingAs($user, 'web')->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('todayRecommendations.0.decision.decision', 'ACCEPTED'));
});

test('the chart receives the current day price series independently of recommendations', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00', 'UTC'));

    try {
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

        $priceDayStart = Carbon::parse('2026-08-10 00:00:00', config('scheduling.timezone'))->utc();
        foreach (range(0, 23) as $hour) {
            ElectricityPrice::factory()->create([
                'hour_start' => $priceDayStart->copy()->addHours($hour),
                'eur_per_mwh' => $hour < 7 ? 50 : 100,
            ]);
        }

        $response = $this->actingAs($user, 'web')->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('dayAheadPrices', 24)
            ->has('todayRecommendations', 1));
    } finally {
        Carbon::setTestNow();
    }
});

test('the dashboard uses the configured schedule timezone for independent today prices and recommendation slides', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00', 'UTC'));

    try {
        config()->set('scheduling.timezone', 'Europe/Helsinki');
        ['user' => $user, 'organization' => $organization] = portalUser();
        app(CurrentOrganization::class)->set($organization);
        $site = Site::factory()->for($organization, 'organization')->create();
        $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create();
        $today = Carbon::parse('2026-08-06 12:00:00', 'Europe/Helsinki')->utc();
        $yesterday = $today->copy()->subDay();
        $tomorrow = $today->copy()->addDay();
        $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
        $todayBlock = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
            'asset_label' => 'Today line',
            'start_at' => $today,
            'end_at' => $today->copy()->addHour(),
        ]);
        $yesterdayBlock = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create([
            'asset_label' => 'Yesterday line',
            'start_at' => $yesterday,
            'end_at' => $yesterday->copy()->addHour(),
        ]);
        Recommendation::factory()->for($run, 'optimizationRun')->for($todayBlock, 'scheduleBlock')->create();
        Recommendation::factory()->for($run, 'optimizationRun')->for($yesterdayBlock, 'scheduleBlock')->create();

        ElectricityPrice::factory()->create([
            'hour_start' => Carbon::parse('2026-08-06 00:00:00', 'Europe/Helsinki')->utc(),
            'eur_per_mwh' => 42,
        ]);
        ElectricityPrice::factory()->create([
            'hour_start' => Carbon::parse('2026-08-05 00:00:00', 'Europe/Helsinki')->utc(),
            'eur_per_mwh' => 99,
        ]);

        $response = $this->actingAs($user, 'web')->get(route('dashboard'));
        $response->assertInertia(fn ($page) => $page
            ->where('chartDateLabel', 'Today — 6 August 2026')
            ->has('dayAheadPrices', 1)
            ->where('dayAheadPrices.0.eur_per_mwh', 42)
            ->where('todayRecommendations.0.rationale', fn (string $value) => $value !== ''));
    } finally {
        Carbon::setTestNow();
    }
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
