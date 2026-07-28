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
 * email notification").
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
