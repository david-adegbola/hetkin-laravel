<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Single source of truth for "which of the three app surfaces (marketing,
 * customer portal, admin console) is this request for" — MIGRATION_PLAN.md
 * Stage 14. Portal/admin are dual-registered (routes/domains.php,
 * routes/web.php): reachable either via a real subdomain
 * (dashboard.{domain}, admin.{domain}) or a path prefix on the main domain
 * (/dashboard/*, /admin/*), so detecting "which surface" needs both checks,
 * not just a host comparison.
 *
 * The host-then-path-prefix check below is not a new pattern — it's the
 * exact logic bootstrap/app.php's redirectGuestsTo()/redirectUsersTo()
 * closures already use, and routes/settings.php is already registered
 * without a domain() constraint for the identical reason (its own docblock
 * explains why). This class exists so that logic exists in exactly one
 * place instead of three (HandleInertiaRequests, the PWA manifest route,
 * the PWA service-worker route all need it).
 */
final class PwaSurface
{
    /**
     * The narrow "is this the real dashboard.{domain} subdomain" check —
     * exposed separately from isPortal() below so
     * App\Http\Middleware\HandleInertiaRequests's existing
     * `subdomain.isDashboardHost` prop (which only ever meant the real
     * subdomain, not the /dashboard prefix fallback too) can delegate here
     * instead of duplicating the comparison.
     */
    public static function isPortalHost(Request $request): bool
    {
        return $request->getHost() === 'dashboard.'.config('app.domain');
    }

    public static function isAdminHost(Request $request): bool
    {
        return $request->getHost() === 'admin.'.config('app.domain');
    }

    public static function isPortal(Request $request): bool
    {
        return self::isPortalHost($request)
            || $request->is('dashboard')
            || $request->is('dashboard/*');
    }

    public static function isAdmin(Request $request): bool
    {
        return self::isAdminHost($request)
            || $request->is('admin')
            || $request->is('admin/*');
    }

    /**
     * @return 'portal'|'admin'|'marketing'
     */
    public static function current(Request $request): string
    {
        if (self::isPortal($request)) {
            return 'portal';
        }

        if (self::isAdmin($request)) {
            return 'admin';
        }

        return 'marketing';
    }

    /**
     * The manifest's start_url / the SW's default scope directory — bare
     * root on a real subdomain, the path prefix on the main domain.
     *
     * @param  'portal'|'admin'  $surface
     */
    public static function startUrlFor(string $surface, Request $request): string
    {
        $isSubdomainHost = $surface === 'portal'
            ? self::isPortalHost($request)
            : self::isAdminHost($request);

        if ($isSubdomainHost) {
            return '/';
        }

        return $surface === 'portal' ? '/dashboard' : '/admin';
    }

    /**
     * Same as startUrlFor() but always trailing-slash-terminated, since a
     * manifest/Service-Worker "scope" is a directory, not a page.
     *
     * @param  'portal'|'admin'  $surface
     */
    public static function scopeFor(string $surface, Request $request): string
    {
        $startUrl = self::startUrlFor($surface, $request);

        return str_ends_with($startUrl, '/') ? $startUrl : $startUrl.'/';
    }
}
