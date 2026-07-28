<?php

namespace App\Notifications;

use App\Domain\Organizations\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the applicant once their pilot application is approved — mirrors
 * the Next.js app's sendInvitationEmail() (src/app/admin/(protected)/
 * pilots/actions.ts). `MAIL_MAILER=log` reproduces v2's own "log instead
 * of send" fallback when RESEND_API_KEY is unset; wiring a real transport
 * is Stage 11 scope.
 */
class PilotApplicationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Invitation $invitation,
        public readonly string $contactName,
        public readonly string $organizationName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviteUrl = route('invite.show', ['invitation' => $this->invitation->id]);

        return (new MailMessage)
            ->subject('Your Hetkin pilot is approved — set up your account')
            ->line("Hi {$this->contactName},")
            ->line("{$this->organizationName}'s pilot application has been approved.")
            ->action('Set up your Hetkin account', $inviteUrl)
            ->line('This link is valid for 14 days.');
    }
}
