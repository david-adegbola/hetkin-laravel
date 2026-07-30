<?php

namespace App\Http\Middleware;

use App\Support\PwaSurface;
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
 * `$realm` is now OPTIONAL — auto-detected from the request's host via
 * PwaSurface::isAdminHost() when omitted, rather than requiring every
 * route to opt in with an explicit `:admin` parameter. This is a
 * defense-in-depth fix, not just a convenience: several customer-realm
 * routes (routes/web.php's `/dashboard` prefix, routes/settings.php, the
 * shared logout/confirm-password routes in routes/auth.php) carry no
 * domain() constraint at all — by original design, so they work on both
 * the main domain and dashboard.{domain} without duplicating every route.
 * That same lack of a constraint means they ALSO match admin.{domain},
 * and since SESSION_DOMAIN is set with a leading dot specifically so the
 * customer session cookie is sent to every subdomain (DEPLOYMENT.md), a
 * browser with a valid customer session visiting admin.{domain} by
 * mistake would previously have had that cookie silently honored there
 * too — the admin realm's isolation existed only for routes that
 * remembered to ask for it. This middleware is now applied globally
 * (bootstrap/app.php), so the realm is correct for every request
 * regardless of which specific route matches, closing that gap for any
 * current or future unconstrained route, not just the ones audited today.
 * Existing explicit `:admin` registrations are unaffected — redundant
 * with the global default now, but harmless (setting the same config
 * values twice).
 *
 * Correctness of this depends on each HTTP request getting a fresh
 * application container (true under standard php-fpm/CGI deployment, NOT
 * true under Laravel Octane or another long-running-worker mode without
 * additional care) — see MIGRATION_PLAN.md ADR-12.
 */
class UseSessionRealm
{
    public function handle(Request $request, Closure $next, ?string $realm = null): Response
    {
        $realm ??= PwaSurface::isAdminHost($request) ? 'admin' : 'customer';

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
