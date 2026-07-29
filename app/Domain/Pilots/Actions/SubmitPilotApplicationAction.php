<?php

namespace App\Domain\Pilots\Actions;

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Organizations\Enums\ProcessType;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Domain\Pilots\Services\IcpScoreCalculator;
use App\Notifications\PilotApplicationSubmitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Ports the Next.js app's submitPilotApplication() Server Action exactly
 * (src/app/pilot-program/apply/actions.ts), minus the rate-limit check
 * itself — that's applied at the route level via the `pilot-application`
 * limiter (App\Providers\AppServiceProvider), not inside this Action, the
 * same way password-reset-link's limiter is route-level rather than
 * Action-level in this codebase.
 *
 * @phpstan-type ValidatedInput array{company_name: string, process_type: string, estimated_annual_spend_eur: int, current_scheduling_method: string, win_condition: ?string, contact_name: string, contact_email: string, contact_phone: ?string}
 */
class SubmitPilotApplicationAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PostHogService $postHog,
    ) {}

    /**
     * @param  ValidatedInput  $input
     */
    public function execute(array $input): PilotApplication
    {
        $processType = ProcessType::from($input['process_type']);
        $icpScore = IcpScoreCalculator::compute($processType, $input['estimated_annual_spend_eur']);

        $application = DB::transaction(function () use ($input, $processType, $icpScore) {
            $application = PilotApplication::create([
                'status' => PilotApplicationStatus::Pending,
                'company_name' => $input['company_name'],
                'process_type' => $processType,
                'estimated_annual_spend_eur' => $input['estimated_annual_spend_eur'],
                'current_scheduling_method' => $input['current_scheduling_method'],
                'win_condition' => ($input['win_condition'] ?? '') ?: null,
                'contact_name' => $input['contact_name'],
                'contact_email' => $input['contact_email'],
                'contact_phone' => ($input['contact_phone'] ?? '') ?: null,
                'icp_score' => $icpScore,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::System,
                actorId: 'pilot-application-form',
                action: 'pilot_application.submitted',
                entityType: 'PilotApplication',
                entityId: $application->id,
                metadata: ['icpScore' => $icpScore, 'processType' => $processType->value],
            ));

            return $application;
        });

        Notification::route('mail', config('mail.pilot_notification_address'))
            ->notify(new PilotApplicationSubmitted($application));

        // Phase 1 §8 funnel: "Visitor → Pilot Request". distinctId is the
        // contact's email, not a user id — no account exists yet at this
        // stage, and using the email keeps this event linkable to the
        // same person's later activity if they sign up with it.
        $this->postHog->capture('pilot_application_submitted', $application->contact_email, [
            'applicationId' => $application->id,
            'icpScore' => $icpScore,
            'processType' => $processType->value,
            'estimatedAnnualSpendEur' => $input['estimated_annual_spend_eur'],
        ]);

        return $application;
    }
}
