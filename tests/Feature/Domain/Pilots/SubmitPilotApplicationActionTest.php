<?php

use App\Domain\Audit\Models\AuditLogEntry;
use App\Domain\Pilots\Actions\SubmitPilotApplicationAction;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Notifications\PilotApplicationSubmitted;
use Illuminate\Support\Facades\Notification;

function validPilotApplicationInput(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Acme Cold Storage Oy',
        'process_type' => 'COLD_STORAGE',
        'estimated_annual_spend_eur' => 200_000,
        'current_scheduling_method' => 'Excel',
        'win_condition' => 'Cut our peak-hour costs.',
        'contact_name' => 'Maria Applicant',
        'contact_email' => 'maria@acme.test',
        'contact_phone' => '+358401234567',
    ], $overrides);
}

test('creates a PENDING application with a computed ICP score', function () {
    Notification::fake();

    $application = app(SubmitPilotApplicationAction::class)->execute(validPilotApplicationInput());

    expect($application)->toBeInstanceOf(PilotApplication::class);
    expect($application->status)->toBe(PilotApplicationStatus::Pending);
    expect($application->icp_score)->toBe(100); // COLD_STORAGE (60) + >=150k spend (40)
    expect($application->contact_email)->toBe('maria@acme.test');
});

test('stores blank optional fields as null', function () {
    Notification::fake();

    $application = app(SubmitPilotApplicationAction::class)->execute(validPilotApplicationInput([
        'win_condition' => '',
        'contact_phone' => '',
    ]));

    expect($application->win_condition)->toBeNull();
    expect($application->contact_phone)->toBeNull();
});

test('writes a SYSTEM-actor audit log entry with no organization', function () {
    Notification::fake();

    $application = app(SubmitPilotApplicationAction::class)->execute(validPilotApplicationInput());

    $entry = AuditLogEntry::query()->latest('created_at')->first();
    expect($entry->action)->toBe('pilot_application.submitted');
    expect($entry->actor_type->value)->toBe('SYSTEM');
    expect($entry->actor_id)->toBe('pilot-application-form');
    expect($entry->organization_id)->toBeNull();
    expect($entry->entity_id)->toBe($application->id);
    expect($entry->metadata['icpScore'])->toBe(100);
});

test('notifies the internal team, not the applicant', function () {
    Notification::fake();

    app(SubmitPilotApplicationAction::class)->execute(validPilotApplicationInput());

    Notification::assertSentOnDemand(
        PilotApplicationSubmitted::class,
        fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === config('mail.pilot_notification_address'),
    );
});
