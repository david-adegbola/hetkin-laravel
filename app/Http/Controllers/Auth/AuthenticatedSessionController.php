<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer-realm sign-in — mirrors the Next.js app's src/app/sign-in/page.tsx
 * + Better Auth's signIn.email() handler. Deliberately outside any
 * auth-required route group, same reasoning as the original: a sign-in
 * page gated by the auth check it exists to satisfy would be an infinite
 * redirect.
 *
 * There is no self-serve signup (see MIGRATION_PLAN.md ADR-6) — this
 * controller only ever authenticates a password an admin-driven invite flow
 * already set.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // TODO(Stage 5 — Scheduling/Portal shell): replace with the real
        // portal redirect. v2's equivalent (src/app/sign-in/page.tsx)
        // pushes to the portal base path once the subdomain rewrite exists
        // (Stage 2); '/dashboard' is the main-domain path it resolves to.
        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
