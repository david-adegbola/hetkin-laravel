<?php

use App\Http\Middleware\EnsureAdminActive;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Middleware\UseSessionRealm;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // App\Http\Middleware\UseSessionRealm must run before
        // Illuminate\Session\Middleware\StartSession resolves the session
        // store — StartSession is one of Laravel's "prioritized" framework
        // middleware, so simply listing UseSessionRealm first in a route's
        // middleware array is NOT enough: Illuminate\Routing\SortedMiddleware
        // repositions prioritized middleware according to the priority list
        // below, regardless of the declared array order, for any middleware
        // not itself in that list. This explicit priority entry is the
        // documented way to guarantee the ordering MIGRATION_PLAN.md ADR-4
        // depends on.
        $middleware->prependToPriorityList(
            before: StartSession::class,
            prepend: UseSessionRealm::class,
        );

        // 'organization.context' — see App\Http\Middleware\
        // ResolveOrganizationContext's docblock. Applied per-route-group
        // (routes/portal.php), not globally: it needs an authenticated
        // web-guard user to resolve an organization for, so it's meaningless
        // outside the portal.
        $middleware->alias([
            'organization.context' => ResolveOrganizationContext::class,
            'admin.role' => EnsureAdminRole::class,
            'admin.active' => EnsureAdminActive::class,
        ]);

        // Two isolated auth realms (MIGRATION_PLAN.md ADR-4): an
        // unauthenticated request to an /admin/* route must land on the
        // admin sign-in page, never the customer one, and vice versa. Same
        // reasoning in the other direction — an already-authenticated
        // visitor hitting a `guest`-only route (e.g. an admin who's already
        // signed in revisiting /admin/login) redirects within their own
        // realm, not to the other one's post-login destination.
        //
        // Also host-aware (MIGRATION_PLAN.md ADR-21, routes/domains.php): a
        // guest on dashboard.{domain} or admin.{domain} redirects to that
        // SAME subdomain's bare sign-in route, not the main domain's
        // prefixed one — bouncing dashboard.hetkin.app to hetkin.app/sign-in
        // would silently change the host in the address bar, which is
        // exactly what the subdomain rewrite exists to avoid.
        $middleware->redirectGuestsTo(function (Request $request) {
            $host = $request->getHost();

            if ($host === 'admin.'.config('app.domain')) {
                return route('admin.subdomain.login');
            }

            if ($request->is('admin*')) {
                return route('admin.login');
            }

            if ($host === 'dashboard.'.config('app.domain')) {
                return route('portal.login');
            }

            return route('login');
        });
        $middleware->redirectUsersTo(function (Request $request) {
            $host = $request->getHost();

            if ($host === 'admin.'.config('app.domain')) {
                return route('admin.subdomain.pilots');
            }

            if ($request->is('admin*')) {
                return route('admin.pilots');
            }

            if ($host === 'dashboard.'.config('app.domain')) {
                return route('portal.dashboard');
            }

            return route('dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
