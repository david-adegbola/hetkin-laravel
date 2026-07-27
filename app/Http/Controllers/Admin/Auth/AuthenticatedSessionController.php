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

        // TODO(Stage 9 — Admin console): replace with the real admin
        // overview redirect. Matches v2's exact post-login destination
        // (src/app/admin/sign-in/page.tsx pushes to `${adminBasePath}/pilots`).
        return redirect()->intended(route('admin.pilots', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
