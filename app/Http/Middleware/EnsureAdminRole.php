<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Enums\AdminRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level equivalent of the Next.js app's requireAdminRole() Server
 * Action helper (src/lib/admin-session.ts) — every admin mutation there
 * names the specific roles allowed to perform it (e.g. `["ADMIN",
 * "SUPPORT"]` for pilot review, `["ADMIN"]` alone for invoice actions).
 * Laravel has no direct equivalent to a Server Action's own inline auth
 * check, so this is a per-route middleware instead: `->middleware('admin
 * .role:ADMIN,SUPPORT')`. Runs after `auth:admin`, so `$request->user
 * ('admin')` is always present here.
 *
 * First real use of admin-realm role gating in an HTTP context in this
 * port — Stage 7's admin-only Actions (baseline locking, invoice
 * lifecycle, etc.) were built and tested directly, with no controller/
 * route wired up yet (Stage 9). This establishes the pattern Stage 9 will
 * reuse for those.
 */
class EnsureAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $admin = $request->user('admin');

        $allowed = array_map(fn (string $role) => AdminRole::from($role), $roles);

        abort_unless($admin && in_array($admin->role, $allowed, true), 403);

        return $next($request);
    }
}
