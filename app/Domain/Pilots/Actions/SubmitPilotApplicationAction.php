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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

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

        // The application row above is already committed by this point —
        // a failure to notify the internal team about it is real (Sentry
        // still sees it, via the catch below) but must never turn an
        // applicant's already-successful submission into a visible 500,
        // same "log instead of crash, never block the real work" posture
        // App\Domain\Analytics\Services\PostHogService's docblock
        // documents for every other optional integration in this app.
        // PilotApplicationSubmitted's own docblock assumed this send could
        // never fail here (it always targets the Resend account owner's
        // inbox) — true only once a sending domain is verified in Resend;
        // an unverified domain rejects sends to any other address, which
        // is exactly what surfaced this as an uncaught 500 in production.
        try {
            Notification::route('mail', config('mail.pilot_notification_address'))
                ->notify(new PilotApplicationSubmitted($application));
        } catch (Throwable $e) {
            Log::error('[pilots] failed to send internal application-submitted notification', ['exception' => $e]);
        }

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
