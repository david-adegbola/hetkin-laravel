<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the admin realm a physically separate session cookie and database
 * table from the customer realm (MIGRATION_PLAN.md ADR-4) — the closest
 * Laravel-native equivalent of the Next.js app having two entirely separate
 * Better Auth instances (src/lib/auth.ts vs src/lib/auth-admin.ts).
 *
 * Must run BEFORE Illuminate\Session\Middleware\StartSession in the
 * middleware pipeline — that's why every admin route is defined with
 * `[UseSessionRealm::class.':admin', 'web']` (this middleware first, 'web'
 * — which contains StartSession — second), rather than relying on the
 * global 'web' group's own ordering. StartSession resolves the session
 * store from whatever `config('session.*')` says AT THE MOMENT it runs; by
 * mutating that config first, the one session this request ends up with is
 * already bound to the admin cookie/table before Laravel ever touches it.
 *
 * Customer-realm routes need no equivalent middleware: the default
 * `config('session.*')` values (from .env) ARE the customer realm's
 * values, so "no swap" is already correct for them.
 *
 * Correctness of this depends on each HTTP request getting a fresh
 * application container (true under standard php-fpm/CGI deployment, NOT
 * true under Laravel Octane or another long-running-worker mode without
 * additional care) — see MIGRATION_PLAN.md ADR-12.
 */
class UseSessionRealm
{
    public function handle(Request $request, Closure $next, string $realm): Response
    {
        if ($realm === 'admin') {
            config([
                'session.cookie' => config('session.admin.cookie'),
                'session.table' => config('session.admin.table'),
                'session.lifetime' => config('session.admin.lifetime'),
            ]);
        }

        return $next($request);
    }
}
