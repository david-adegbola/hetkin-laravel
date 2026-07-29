<?php

namespace App\Notifications;

use App\Domain\Auth\Enums\AdminRole;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by App\Domain\Auth\Actions\CreateAdminUserAction — mirrors the Next.
 * js app's createAdminUser() Server Action's inline email (src/app/admin/
 * (protected)/team/actions.ts). Deliberately does NOT contain the temporary
 * password — that's shown once in the creating admin's own UI and must be
 * relayed to the new admin out of band, "for security reasons" per v2's
 * own copy.
 *
 * Deliberately NOT `ShouldQueue` — see App\Notifications\
 * PilotApplicationSubmitted's docblock for why every notification in this
 * app sends synchronously, matching v2's own inline-awaited email sends
 * exactly.
 */
class AdminAccountCreated extends Notification
{
    public function __construct(
        public readonly string $recipientName,
        public readonly AdminRole $role,
        public readonly string $creatorName,
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
        return (new MailMessage)
            ->subject('Your Hetkin admin account has been created')
            ->line("Hi {$this->recipientName},")
            ->line("An internal Hetkin admin account (role: {$this->role->value}) has been created for you by {$this->creatorName}.")
            ->line('Ask them directly for your temporary sign-in credentials — they are not included in this email for security reasons.')
            ->action('Sign in to Hetkin admin', route('admin.login'));
    }
}
