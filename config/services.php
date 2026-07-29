<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'entsoe' => [
        'token' => env('ENTSO_E_TOKEN'),
    ],

    // Server-side capture (App\Domain\Analytics\Services\PostHogService) —
    // mirrors v2's own project key exactly, just without the `NEXT_PUBLIC_`
    // prefix, since that prefix only ever meant "also bundle this into the
    // client build" (see resources/js/app.tsx's VITE_POSTHOG_KEY/HOST for
    // that half) and has no meaning for a server-only PHP read. Both env
    // vars are set to the same real PostHog project key/host in production
    // — see MIGRATION_PLAN.md's Stage 11 ADR.
    'posthog' => [
        'key' => env('POSTHOG_KEY'),
        'host' => env('POSTHOG_HOST', 'https://eu.i.posthog.com'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
