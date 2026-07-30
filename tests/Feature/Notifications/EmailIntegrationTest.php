<?php

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Pilots\Models\PilotApplication;
use App\Notifications\InvitationResent;
use App\Notifications\PilotApplicationSubmitted;

test('PilotApplicationSubmitted uses the app-wide MAIL_FROM_ADDRESS like every other notification', function () {
    // Used to hardcode onboarding@resend.dev (Resend's unverified sandbox
    // sender), reasoning that it was safe since this email only ever
    // targets CONTACT_EMAIL — that assumption didn't hold in production
    // (Resend's own logs showed the same "domain not verified" failure on
    // that sender), so there's no reason to keep a second, unreliable
    // sender identity around once hetkin.app is verified for everything
    // else.
    $application = PilotApplication::factory()->make();

    $mailMessage = (new PilotApplicationSubmitted($application))->toMail((object) []);

    expect($mailMessage->from)->toBeEmpty();
});

test('every other outbound notification uses the app-wide MAIL_FROM_ADDRESS, not a per-notification override', function () {
    config(['mail.from.address' => 'notifications@hetkin.app', 'mail.from.name' => 'Hetkin']);

    $invitation = Invitation::factory()->create();
    $mailMessage = (new InvitationResent($invitation, 'Acme Oy'))->toMail((object) []);

    expect($mailMessage->from)->toBeEmpty();
});

test('the resend mailer config reads RESEND_API_KEY, matching v2\'s own env var name exactly', function () {
    config(['services.resend.key' => 'test-resend-key']);

    expect(config('services.resend.key'))->toBe('test-resend-key');
});

test('the pilot notification address falls back to hello@hetkin.app when CONTACT_EMAIL is unset, matching v2\'s default exactly', function () {
    expect(config('mail.pilot_notification_address'))->toBe('hello@hetkin.app');
});
