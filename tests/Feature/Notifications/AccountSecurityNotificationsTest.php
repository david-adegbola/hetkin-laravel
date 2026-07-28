<?php

use App\Notifications\LoggedOutAllDevices;
use App\Notifications\PasswordChanged;
use App\Notifications\PasswordResetCompleted;
use App\Notifications\PasswordResetRequested;
use Illuminate\Contracts\Queue\ShouldQueue;

test('every account-security notification is queued', function () {
    expect(new PasswordResetRequested('https://example.test/reset'))
        ->toBeInstanceOf(ShouldQueue::class);
    expect(new PasswordResetCompleted)->toBeInstanceOf(ShouldQueue::class);
    expect(new PasswordChanged(hadOtherActiveSessions: false))->toBeInstanceOf(ShouldQueue::class);
    expect(new LoggedOutAllDevices)->toBeInstanceOf(ShouldQueue::class);
});

test('PasswordResetRequested renders with the reset URL and expiry notice', function () {
    $mail = (new PasswordResetRequested('https://hetkin.test/reset-password/abc?email=user@example.test'))
        ->toMail((object) []);

    expect($mail->subject)->toBe('Reset your Hetkin password');
    expect($mail->actionUrl)->toBe('https://hetkin.test/reset-password/abc?email=user@example.test');
});

test('PasswordChanged mentions other devices only when they existed', function () {
    $withOthers = (new PasswordChanged(hadOtherActiveSessions: true))->toMail((object) []);
    $withoutOthers = (new PasswordChanged(hadOtherActiveSessions: false))->toMail((object) []);

    $withOthersText = collect($withOthers->introLines)->implode(' ');
    $withoutOthersText = collect($withoutOthers->introLines)->implode(' ');

    expect($withOthersText)->toContain('another device or browser');
    expect($withoutOthersText)->not->toContain('another device or browser');
});
