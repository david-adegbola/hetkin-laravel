<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Domain
    |--------------------------------------------------------------------------
    |
    | The apex domain the dashboard.* and admin.* subdomain route groups
    | (routes/domains.php, MIGRATION_PLAN.md ADR-21) are built from — e.g.
    | "hetkin.app" in production, so requests to dashboard.hetkin.app and
    | admin.hetkin.app resolve. Defaults to "localhost", which every modern
    | browser resolves *.localhost subdomains against automatically (no
    | /etc/hosts edit needed) — visit http://dashboard.localhost:8000 and
    | http://admin.localhost:8000 in local dev. Kept separate from `url`
    | above since APP_URL may carry a scheme/port that Route::domain()
    | patterns must not include.
    |
    */

    'domain' => env('APP_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | Purely informational — shared to the frontend as `appVersion`
    | (App\Http\Middleware\HandleInertiaRequests) for display purposes
    | (e.g. a support/debug "which build is this" indicator). NOT what
    | drives PWA update detection (MIGRATION_PLAN.md Stage 14) — that's the
    | browser's own native Service Worker install/waiting lifecycle
    | (resources/js/lib/pwa.ts), which reacts to the SW script's bytes
    | actually changing and needs no version string to compare. Keeping
    | those two concerns separate avoids a real failure mode: a value
    | computed independently at JS build time vs. PHP request time can
    | silently disagree the moment a deploy ships built assets from a
    | slimmer runtime image without `.git` — see docs/pwa.md. The
    | `shell_exec` git fallback is therefore LOCAL-only; production should
    | set APP_VERSION explicitly (e.g. from CI's commit SHA), or this
    | reads the obviously-a-placeholder 'dev' rather than silently guessing.
    |
    | Reads APP_ENV directly via env() rather than app()->environment() —
    | config files load before the container's environment detection is
    | safely callable this early in the boot sequence.
    |
    */

    'version' => env('APP_VERSION') ?: (
        env('APP_ENV', 'production') === 'local'
            ? trim((string) @shell_exec('git rev-parse --short HEAD 2>/dev/null'))
            : null
    ) ?: 'dev',

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
