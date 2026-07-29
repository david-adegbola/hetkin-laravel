<?php

namespace App\Notifications;

use App\Domain\Organizations\Models\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by App\Domain\Organizations\Actions\ResendInvitationAction — mirrors
 * the Next.js app's resendInvitation() Server Action's inline email (src/
 * app/admin/(protected)/organizations/[id]/actions.ts). Reuses the same
 * still-pending invitation row/link as the original invite — this
 * notification never creates a new Invitation or extends `expires_at`.
 *
 * Deliberately NOT `ShouldQueue` — see App\Notifications\
 * PilotApplicationSubmitted's docblock for why every notification in this
 * app sends synchronously, matching v2's own inline-awaited email sends
 * exactly.
 */
class InvitationResent extends Notification
{
    public function __construct(
        public readonly Invitation $invitation,
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

        $expiryLine = $this->invitation->expires_at->isFuture()
            ? "This link is valid until {$this->invitation->expires_at->format('d/m/Y')}."
            : 'This link has expired — contact us for a new one.';

        return (new MailMessage)
            ->subject("Reminder: set up your Hetkin account for {$this->organizationName}")
            ->line('Hi,')
            ->line("This is a reminder to set up your Hetkin account for {$this->organizationName}.")
            ->action('Set up your Hetkin account', $inviteUrl)
            ->line($expiryLine);
    }
}
