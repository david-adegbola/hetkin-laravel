<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Laravel's standard password-confirmation flow (`password.confirm`
 * middleware, backed by Illuminate\Auth\Middleware\RequirePassword) — a
 * re-auth checkpoint before sensitive actions, separate from the session
 * itself expiring. Applied today to change-password and session-revocation
 * (App\Http\Controllers\Settings\{Password,Session}Controller); the brief
 * names org-ownership/billing/account-deletion as future adopters once
 * those features exist.
 */
class ConfirmablePasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->ensurePasswordMatches();

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('settings.security', absolute: false));
    }
}
