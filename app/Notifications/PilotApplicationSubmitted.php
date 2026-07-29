<?php

namespace App\Notifications;

use App\Domain\Pilots\Models\PilotApplication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Internal "new pilot application" notification — mirrors the Next.js
 * app's notifyTeam() (src/app/pilot-program/apply/actions.ts). Sent
 * on-demand to config('mail.pilot_notification_address'), not to the
 * applicant.
 *
 * Deliberately hardcodes its own `from` address rather than using the
 * app-wide `MAIL_FROM_ADDRESS` (config/mail.php's `from`, matching v2's
 * `RESEND_FROM_ADDRESS`) — v2's notifyTeam() does the identical thing,
 * always sending from the unverified-domain `onboarding@resend.dev`
 * rather than the verified `notifications@hetkin.app` every other
 * outbound email uses. That's safe specifically for this one email only
 * because it always sends to `CONTACT_EMAIL` (the account owner's own
 * inbox) — Resend restricts an unverified domain to sending only to the
 * account owner's own verified address, so unlike the customer-facing
 * invite emails, this internal one was never blocked by domain
 * verification and never needed the fix.
 *
 * Deliberately NOT `ShouldQueue` — v2 always awaits its email send inline,
 * as part of the same request/response cycle, with no queue system at
 * all. A queued notification here would silently never send in any
 * environment without a running queue worker (this app's own local
 * default `QUEUE_CONNECTION=database` has none by default) — a real
 * behavior regression from v2 a live smoke test caught, not a
 * theoretical one. See MIGRATION_PLAN.md's Stage 11 ADR.
 */
class PilotApplicationSubmitted extends Notification
{
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
            ->from('onboarding@resend.dev', 'Hetkin pilot applications')
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
