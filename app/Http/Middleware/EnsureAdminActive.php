<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level equivalent of the Next.js app's two independent, redundant
 * `active` re-checks on every admin request (src/app/admin/(protected)/
 * layout.tsx and src/lib/admin-session.ts's requireAdminSession()) — see
 * App\Http\Requests\Admin\Auth\LoginRequest's docblock, which anticipates
 * this middleware as "the check for already-authenticated admin sessions."
 *
 * v2 needs two separate checks because Better Auth's session cookieCache
 * can serve a stale `active: true` for up to 30s without hitting the DB.
 * Laravel's session guard has no equivalent cache — Auth::user() resolves
 * the AdminUser row fresh from the DB on every request already — so a
 * single middleware here gives the same request-level guarantee v2 gets
 * from its two call sites, without needing to duplicate the check.
 *
 * A deactivated admin is logged out outright (not just blocked) — any
 * further request would fail this same check anyway, and an invalidated
 * session is the more honest state to leave them in than a technically-
 * still-authenticated one that every route silently rejects.
 */
class EnsureAdminActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && ! $admin->active) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'This admin account has been deactivated. Contact another admin.');
        }

        return $next($request);
    }
}
