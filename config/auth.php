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
 * No `passwords` broker is configured for either realm — v2 has no
 * forgot-password flow for either realm (MIGRATION_PLAN.md ADR-6), so
 * there is nothing for Laravel's password-reset subsystem to serve.
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

];
