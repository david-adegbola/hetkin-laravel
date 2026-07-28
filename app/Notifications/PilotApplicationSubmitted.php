<?php

namespace App\Notifications;

use App\Domain\Pilots\Models\PilotApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal "new pilot application" notification — mirrors the Next.js
 * app's notifyTeam() (src/app/pilot-program/apply/actions.ts). Sent
 * on-demand to config('mail.pilot_notification_address'), not to the
 * applicant. `MAIL_MAILER=log` (this app's local-dev default) reproduces
 * v2's own "log the submission instead" fallback when RESEND_API_KEY is
 * unset — wiring a real transport is Stage 11 scope
 * (MIGRATION_PLAN.md's Stage 11).
 */
class PilotApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly PilotApplication $application) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = $this->application;

        $message = (new MailMessage)
            ->subject("[Hetkin] Pilot application — {$app->company_name} (ICP {$app->icp_score})")
            ->line('NEW PILOT APPLICATION')
            ->line("Company: {$app->company_name}")
            ->line('Process type: '.$app->process_type->label())
            ->line('Est. annual spend: €'.number_format($app->estimated_annual_spend_eur))
            ->line("Scheduling today: {$app->current_scheduling_method}");

        if ($app->win_condition) {
            $message->line("Win condition: {$app->win_condition}");
        }

        return $message
            ->line("Contact: {$app->contact_name} <{$app->contact_email}>".($app->contact_phone ? " · {$app->contact_phone}" : ''))
            ->line("ICP score: {$app->icp_score}/100")
            ->line("Application ID: {$app->id}");
    }
}
