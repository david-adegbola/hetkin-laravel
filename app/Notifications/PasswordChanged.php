<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent after App\Domain\Auth\Actions\ChangePasswordAction completes.
 *
 * `hadOtherActiveSessions` is this module's "changed from another device"
 * detection (Account Security brief: "if detectable") — genuinely derived
 * from data this app actually has (other rows in the `sessions` table at
 * the moment of change), not invented IP-history tracking. It answers "was
 * this account signed in elsewhere when the password changed," which is
 * the detectable signal, not "which physical device" — that distinction is
 * called out explicitly in the email copy rather than overclaiming.
 *
 * ShouldQueue: a deliberate, tested Account Security requirement (see
 * App\Notifications\PasswordResetRequested's docblock) — not v2 parity.
 */
class PasswordChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly bool $hadOtherActiveSessions) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your Hetkin password was changed')
            ->line('Your Hetkin account password was just changed.');

        if ($this->hadOtherActiveSessions) {
            $message->line('Your account was signed in on another device or browser at the time of this change.');
        }

        return $message
            ->line("If you didn't make this change, contact support immediately.");
    }
}
