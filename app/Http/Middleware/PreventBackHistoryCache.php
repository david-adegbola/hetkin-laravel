<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevents authenticated pages from being restorable after logout via the
 * browser's back-forward cache (bfcache) or regular HTTP cache — see
 * MIGRATION_PLAN.md ADR-14 for the full root-cause analysis. Applied ONLY
 * to authenticated route groups (routes/settings.php, routes/portal.php,
 * routes/admin-protected.php), never globally — marketing pages, docs, and
 * static assets must stay cacheable, which is exactly why this is
 * middleware scoped to a route group rather than a blanket response header
 * set in bootstrap/app.php.
 *
 * `no-store` is the directive that actually disqualifies a response from
 * bfcache in modern browsers (Chrome, Firefox, Safari all key off it) —
 * `no-cache`/`must-revalidate`/`private` are included for older caches and
 * intermediary proxies that don't honor `no-store` alone. `Pragma:
 * no-cache` and `Expires: 0` are there purely for HTTP/1.0 compatibility;
 * no realistic client relies on them today, but they cost nothing and the
 * brief asks for them explicitly.
 *
 * This can only prevent FUTURE caching of a response served through this
 * middleware — it cannot retroactively un-cache a page a browser already
 * stored under different headers. See resources/js/app.tsx's `pageshow`
 * listener for the complementary client-side handling of that edge case.
 */
class PreventBackHistoryCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
