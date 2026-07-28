<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use Illuminate\Support\Carbon;

test('the reports page lists assessments and verifications', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();
    VerificationRecord::factory()->for($organization, 'organization')->for($baseline)->create();

    $response = $this->actingAs($user, 'web')->get(route('reports'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/Reports')
        ->has('assessments', 1)
        ->has('verifications', 1));
});

test('generating an assessment with no meter data redirects back with an error', function () {
    ['user' => $user] = portalUser();

    $response = $this->actingAs($user, 'web')->post(route('reports.generate'));

    $response->assertSessionHasErrors('assessment');
});

test('generating a valid assessment redirects back with a success status', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    for ($d = 0; $d < 5; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = Carbon::parse('2026-01-01', 'UTC')->addDays($d)->addHours($h);
            MeterReading::factory()->for($point, 'meteringPoint')->create([
                'hour_start' => $hourStart,
                'consumption_kwh' => 100,
            ]);
            ElectricityPrice::factory()->create([
                'bidding_zone' => BiddingZone::FI,
                'hour_start' => $hourStart,
                'eur_per_mwh' => 50,
                'source' => PriceSource::Entsoe,
            ]);
        }
    }

    $response = $this->actingAs($user, 'web')->post(route('reports.generate'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'assessment-generated');
});

test('an assessment belonging to another organization 404s', function () {
    ['user' => $user] = portalUser();
    $otherOrganization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($otherOrganization);
    $baseline = Baseline::factory()->for($otherOrganization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($otherOrganization, 'organization')->for($baseline)->create();

    $response = $this->actingAs($user, 'web')->get(route('reports.show', $assessment));

    $response->assertNotFound();
});

test('the report detail page renders the view-model for the customer\'s own assessment', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();

    $response = $this->actingAs($user, 'web')->get(route('reports.show', $assessment));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Portal/ReportDetail')
        ->has('vm.cover')
        ->has('vm.savings'));
});

test('downloading the PDF streams a PDF and writes an audit log entry', function () {
    ['user' => $user, 'organization' => $organization] = portalUser();
    app(CurrentOrganization::class)->set($organization);
    $baseline = Baseline::factory()->for($organization, 'organization')->create();
    $assessment = SavingsAssessment::factory()->for($organization, 'organization')->for($baseline)->create();

    $response = $this->actingAs($user, 'web')->get(route('reports.pdf', $assessment));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('cache-control'))->toContain('no-store');

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('savings_assessment.pdf_downloaded');
    expect($entry->entity_id)->toBe($assessment->id);
});

test('a non-member cannot reach the reports page', function () {
    $response = $this->get(route('reports'));

    $response->assertRedirect(route('login'));
});
