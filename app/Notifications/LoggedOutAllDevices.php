<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent after App\Domain\Auth\Actions\LogoutOtherDevicesAction removes every
 * session row except the caller's current one.
 */
class LoggedOutAllDevices extends Notification implements ShouldQueue
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
            ->subject('You were signed out of other devices')
            ->line('All other browser sessions on your Hetkin account were just signed out.')
            ->line('This device remains signed in.')
            ->line("If you didn't do this, contact support immediately.");
    }
}
