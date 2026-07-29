<?php

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Billing\Actions\GenerateSavingsAssessmentAction;
use App\Domain\Billing\Actions\IssueInvoiceAction;
use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Metering\Actions\UploadMeterReadingsAction;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Metering\Models\Site;
use App\Domain\Optimization\Actions\ApplyDecisionsToNewScheduleVersionAction;
use App\Domain\Optimization\Actions\DecideRecommendationAction;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Models\OptimizationRun;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\Actions\AcceptInvitationAction;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Models\Member;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Actions\ApprovePilotApplicationAction;
use App\Domain\Pilots\Actions\SubmitPilotApplicationAction;
use App\Domain\Pilots\Models\PilotApplication;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ProductionSchedule;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * Spot-checks each of the nine real v2 captureServerEvent() call sites
 * (src/lib/analytics/server.ts's callers) fires with the exact event name,
 * distinctId, and properties v2 uses — see MIGRATION_PLAN.md's Stage 11
 * ADR. Each Action's own test file already covers its domain behavior;
 * this file only covers the PostHog side effect layered on top.
 */
function meterCsvFixture(): string
{
    $lines = ['Start time,Quantity'];
    $ts = new DateTimeImmutable('2024-01-01T00:00');
    for ($i = 0; $i < 24; $i++) {
        $lines[] = $ts->modify("+{$i} hours")->format('Y-m-d\TH:i').',10.0';
    }

    return implode("\n", $lines);
}

test('IssueInvoiceAction fires invoice_issued, org-scoped', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->for($organization, 'organization')->create(['total_cents' => 70_000]);
    $this->mock(PostHogService::class, function ($mock) use ($organization, $invoice) {
        $mock->shouldReceive('capture')->once()->with('invoice_issued', $organization->id, [
            'organizationId' => $organization->id,
            'invoiceId' => $invoice->id,
            'totalCents' => 70_000,
        ]);
    });

    app(IssueInvoiceAction::class)->execute($invoice->id, $admin);
});

test('IssueInvoiceAction does not fire the event for a non-DRAFT invoice (no-op path)', function () {
    $organization = Organization::factory()->create();
    $admin = AdminUser::factory()->create();
    $invoice = Invoice::factory()->paid()->for($organization, 'organization')->create();
    $this->mock(PostHogService::class, function ($mock) {
        $mock->shouldReceive('capture')->never();
    });

    app(IssueInvoiceAction::class)->execute($invoice->id, $admin);
});

test('ApprovePilotApplicationAction fires pilot_application_approved, keyed by contact email', function () {
    Notification::fake();
    $application = PilotApplication::factory()->create(['contact_email' => 'maria@acme.test']);
    $admin = AdminUser::factory()->create();
    $this->mock(PostHogService::class, function ($mock) use ($application) {
        $mock->shouldReceive('capture')->once()->with(
            'pilot_application_approved',
            'maria@acme.test',
            Mockery::on(fn ($props) => $props['applicationId'] === $application->id
                && $props['processType'] === $application->process_type->value),
        );
    });

    app(ApprovePilotApplicationAction::class)->execute($application, $admin);
});

test('SubmitPilotApplicationAction fires pilot_application_submitted, keyed by contact email', function () {
    $this->mock(PostHogService::class, function ($mock) {
        $mock->shouldReceive('capture')->once()->with(
            'pilot_application_submitted',
            'maria@acme.test',
            Mockery::on(fn ($props) => $props['icpScore'] === 100 && $props['estimatedAnnualSpendEur'] === 200_000),
        );
    });

    app(SubmitPilotApplicationAction::class)->execute([
        'company_name' => 'Acme Cold Storage Oy',
        'process_type' => 'COLD_STORAGE',
        'estimated_annual_spend_eur' => 200_000,
        'current_scheduling_method' => 'Excel',
        'win_condition' => null,
        'contact_name' => 'Maria Applicant',
        'contact_email' => 'maria@acme.test',
        'contact_phone' => null,
    ]);
});

test('AcceptInvitationAction fires onboarding_completed with a PostHog $set for the email', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);
    $this->mock(PostHogService::class, function ($mock) use ($invitation, $user) {
        $mock->shouldReceive('capture')->once()->with('onboarding_completed', $user->id, [
            'organizationId' => $invitation->organization_id,
            '$set' => ['email' => 'person@example.test'],
        ]);
    });

    app(AcceptInvitationAction::class)->execute($invitation, $user);
});

test('AcceptInvitationAction does not fire the event when already a member (no-op path)', function () {
    $invitation = Invitation::factory()->create(['email' => 'person@example.test']);
    $user = User::factory()->create(['email' => 'person@example.test']);
    app(CurrentOrganization::class)->set(Organization::withoutGlobalScope(OrganizationScope::class)->find($invitation->organization_id));
    Member::create([
        'organization_id' => $invitation->organization_id,
        'user_id' => $user->id,
        'role' => $invitation->role,
    ]);
    $this->mock(PostHogService::class, function ($mock) {
        $mock->shouldReceive('capture')->never();
    });

    app(AcceptInvitationAction::class)->execute($invitation, $user);
});

test('DecideRecommendationAction fires recommendation_decision with hasReason', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->create();
    $this->mock(PostHogService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('capture')->once()->with('recommendation_decision', Mockery::type('string'), [
            'organizationId' => $organization->id,
            'decision' => 'REJECTED',
            'hasReason' => true,
        ]);
    });

    app(DecideRecommendationAction::class)->execute(
        $organization, $user, $recommendation->id, RecommendationDecisionType::Rejected, 'Too tight a window', null, null,
    );
});

test('ApplyDecisionsToNewScheduleVersionAction fires schedule_version_applied', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $schedule = ProductionSchedule::factory()->for($organization, 'organization')->create(['version' => 1]);
    $block = ScheduleBlock::factory()->for($schedule, 'schedule')->for($site)->create();
    $run = OptimizationRun::factory()->for($organization, 'organization')->for($schedule, 'schedule')->create();
    $recommendation = Recommendation::factory()->for($run, 'optimizationRun')->for($block, 'scheduleBlock')->create();
    RecommendationDecision::factory()->for($recommendation)->create();
    $this->mock(PostHogService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('capture')->once()->with('schedule_version_applied', Mockery::type('string'), [
            'organizationId' => $organization->id,
            'newVersion' => 2,
            'blocksChanged' => 1,
        ]);
    });

    app(ApplyDecisionsToNewScheduleVersionAction::class)->execute($organization, $user);
});

test('UploadMeterReadingsAction fires meter_data_uploaded with the row count', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $this->mock(PostHogService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('capture')->once()->with('meter_data_uploaded', Mockery::type('string'), [
            'organizationId' => $organization->id,
            'rowCount' => 24,
        ]);
    });

    app(UploadMeterReadingsAction::class)
        ->execute($organization, $user, meterCsvFixture(), 'export.csv');
});

test('GenerateSavingsAssessmentAction fires savings_assessment_generated', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    for ($h = 0; $h < 24 * 5; $h++) {
        $hourStart = Carbon::parse('2026-01-01', 'UTC')->addHours($h);
        MeterReading::factory()->for($point, 'meteringPoint')->create(['hour_start' => $hourStart, 'consumption_kwh' => 10]);
        ElectricityPrice::factory()->create([
            'bidding_zone' => BiddingZone::FI, 'hour_start' => $hourStart, 'eur_per_mwh' => 50, 'source' => PriceSource::Entsoe,
        ]);
    }
    $this->mock(PostHogService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('capture')->once()->with(
            'savings_assessment_generated',
            Mockery::type('string'),
            Mockery::on(fn ($props) => $props['organizationId'] === $organization->id && array_key_exists('isProvisional', $props)),
        );
    });

    app(GenerateSavingsAssessmentAction::class)->execute($organization, $user);
});

test('RunVerificationAction fires verification_computed per org, unconditionally', function () {
    $organization = Organization::factory()->create();
    app(CurrentOrganization::class)->set($organization);
    Baseline::factory()->locked()->for($organization, 'organization')->create(['baseline_annual_cost_cents' => 10_950_000]);
    $site = Site::factory()->for($organization, 'organization')->create();
    $point = MeteringPoint::factory()->for($site)->create();
    $periodStart = Carbon::parse('2026-01-01', 'UTC');
    for ($d = 0; $d < 31; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $hourStart = $periodStart->copy()->addDays($d)->addHours($h);
            MeterReading::factory()->for($point, 'meteringPoint')->create(['hour_start' => $hourStart, 'consumption_kwh' => 100]);
            ElectricityPrice::factory()->create([
                'bidding_zone' => BiddingZone::FI, 'hour_start' => $hourStart, 'eur_per_mwh' => 50, 'source' => PriceSource::Entsoe,
            ]);
        }
    }
    $this->mock(PostHogService::class, function ($mock) use ($organization) {
        $mock->shouldReceive('capture')->once()->with(
            'verification_computed',
            $organization->id,
            Mockery::on(fn ($props) => $props['organizationId'] === $organization->id && array_key_exists('varianceFlagged', $props)),
        );
    });

    app(RunVerificationAction::class)->execute($periodStart, Carbon::parse('2026-02-01', 'UTC'));
});
