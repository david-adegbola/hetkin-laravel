<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Services\ActiveSessionFinder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Security (Account Security module). Read-only render; every
 * mutation (change password, revoke session, logout other devices) has its
 * own controller/route, kept thin and single-purpose rather than folded
 * into one do-everything settings controller.
 */
class SecurityController extends Controller
{
    public function show(Request $request, ActiveSessionFinder $activeSessionFinder): Response
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        return Inertia::render('Settings/Security', [
            'passwordChangedAt' => $user->password_changed_at?->toIso8601String(),
            // Computed server-side deliberately, not derived from
            // passwordChangedAt client-side: "days since X" needs the
            // current time, and React 19's render-purity rule forbids
            // calling Date.now() (even memoized) during render — the
            // current instant has to come from somewhere outside the
            // component tree. This is also just where "is this password
            // stale" belongs as a piece of business logic either way.
            'passwordAgeDays' => $user->password_changed_at
                ?->diffInDays(now()),
            'sessions' => $activeSessionFinder
                ->forUser($user, $request->session()->getId())
                ->map->toArray()
                ->values(),
        ]);
    }
}
