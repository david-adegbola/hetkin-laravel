<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\LoginAction;
use App\Domain\Auth\Actions\LogoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
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
 * controller only ever authenticates a password an admin-driven invite flow,
 * or this module's reset/change-password flows, already set.
 */
class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request, LoginAction $action): RedirectResponse
    {
        $request->authenticate();

        // Session-fixation prevention: a new session ID for every login,
        // never reusing whatever pre-auth session ID the guest happened to
        // have. Must run before the intended-redirect lookup below reads
        // from that (now-regenerated) session.
        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $action->execute($user);

        // Host-aware since Stage 2 (MIGRATION_PLAN.md ADR-21): a login
        // submitted on dashboard.{domain} lands back on that same
        // subdomain's bare root, not the main domain's prefixed
        // "/dashboard" — matches v2's post-login push. Real portal
        // destination since Stage 5 (routes/portal.php).
        $target = $request->getHost() === 'dashboard.'.config('app.domain')
            ? route('portal.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect()->intended($target);
    }

    public function destroy(Request $request, LogoutAction $action): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        if ($user) {
            $action->execute($user);
        }

        // SessionGuard::logout() already forgets the remember-me recaller
        // cookie internally (Laravel core behavior, not something this
        // controller needs to duplicate) before clearing the guard's user.
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
