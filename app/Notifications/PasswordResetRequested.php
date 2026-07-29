<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent by App\Models\User::sendPasswordResetNotification() (overriding
 * Laravel's default Illuminate\Auth\Notifications\ResetPassword) so this
 * module controls content/branding while still riding entirely on the
 * framework's own Password Broker for the token itself — this class never
 * generates or validates a token, it only renders the email around one
 * Password::sendResetLink() already created.
 *
 * ShouldQueue: the request-a-reset-link response must return immediately
 * regardless of mail-provider latency (Account Security brief: "Queued
 * email notification") — a deliberate, tested requirement (see
 * AccountSecurityNotificationsTest's "every account-security notification
 * is queued"), not v2 parity: Account Security was an out-of-band addition
 * ahead of Stage 2, not a straight port. A Stage 11 live smoke test found
 * that no queue worker actually runs anywhere in this app's current dev
 * setup (no Supervisor/systemd config, nothing in .claude/launch.json),
 * so `QUEUE_CONNECTION=database` only persists a job row today rather than
 * ever processing it — a real production-readiness gap, but the fix is
 * standing up a queue worker (Stage 15 territory), not un-queuing this
 * notification against its own tested spec.
 */
class PasswordResetRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $resetUrl) {}

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
            ->subject('Reset your Hetkin password')
            ->line('We received a request to reset the password for your Hetkin account.')
            ->action('Reset password', $this->resetUrl)
            ->line('This link expires in 60 minutes.')
            ->line("If you didn't request a password reset, no action is needed — your password hasn't been changed.");
    }
}
