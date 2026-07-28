<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent after App\Domain\Auth\Actions\ResetPasswordAction completes
 * successfully — confirms the change to the account owner regardless of
 * whether they were the one who actually performed it (the standard
 * "if this wasn't you, contact support" security pattern).
 */
class PasswordResetCompleted extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Your Hetkin password has been reset')
            ->line('Your Hetkin account password was just reset.')
            ->line('You can now sign in with your new password.')
            ->line("If you didn't make this change, contact support immediately.");
    }
}
