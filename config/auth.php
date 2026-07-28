<?php

use App\Models\AdminUser;
use App\Models\User;

/**
 * Two completely isolated authentication realms, matching the Next.js
 * app's two separate Better Auth instances (src/lib/auth.ts,
 * src/lib/auth-admin.ts) — see MIGRATION_PLAN.md ADR-4. Neither guard's
 * session/cookie is ever valid against the other; App\Http\Middleware\
 * UseSessionRealm is what makes the 'admin' guard's session physically
 * separate (different cookie, different DB table) rather than just a
 * different provider on the same session.
 *
 * `passwords.users` backs the customer-realm forgot/reset-password flow
 * (Account Security module — see MIGRATION_PLAN.md ADR-13). No admin-realm
 * broker: that module is customer-portal-only per its brief; the admin
 * realm still has no self-serve reset flow.
 */
return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admin_users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'admin_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_ADMIN_MODEL', AdminUser::class),
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            // 60 minutes: expiring reset tokens per the Account Security
            // brief. Laravel's Password::reset() enforces this itself —
            // an expired token fails the broker's own token check before
            // any password mutation is attempted.
            'expire' => 60,
            // Minimum seconds between two reset-link requests for the same
            // email — Laravel's own throttle, on top of the route-level
            // RateLimiter (see routes/auth.php).
            'throttle' => 60,
        ],
    ],

];
