<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Internal admin sign-in — mirrors the Next.js app's
 * src/app/admin/sign-in/page.tsx. No AdminUser row exists in a fresh
 * database; the first one must be inserted directly (a seeder, in Stage 9's
 * Team domain) before this page is reachable in practice — admin
 * provisioning is a deliberate action, not self-serve, same as v2.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Host-aware since Stage 2 (MIGRATION_PLAN.md ADR-21): matches v2's
        // exact post-login destination (src/app/admin/sign-in/page.tsx
        // pushes to `${adminBasePath}/pilots`, computed from the current
        // host at redirect time — same idea here). Deliberately still
        // /pilots even now that Stage 9's Overview page
        // (App\Http\Controllers\Admin\DashboardController) exists — v2's
        // own admin root page comment confirms this was an earlier
        // deliberate change, not reverted: the Overview page is reachable
        // from the nav, but isn't the sign-in destination.
        $target = $request->getHost() === 'admin.'.config('app.domain')
            ? route('admin.subdomain.pilots', absolute: false)
            : route('admin.pilots', absolute: false);

        return redirect()->intended($target);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // See App\Http\Controllers\Auth\AuthenticatedSessionController's
        // destroy() for why this is needed — same fix, same reasoning,
        // admin realm.
        Inertia::clearHistory();

        return redirect()->route('admin.login');
    }
}
