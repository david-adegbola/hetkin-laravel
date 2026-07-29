<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Models\AuditLogEntry;
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
            // Timestamps only — App\Domain\Auth\Actions\LoginAction writes
            // an 'auth.login' AuditLogEntry per successful sign-in but
            // doesn't capture IP/user-agent on it (unlike SessionRecord,
            // which does), so there's no device/location to show here
            // without inventing data the architecture doesn't actually
            // collect at this point. A direct query, not a new service —
            // AuditLogService only ever writes (see its own docblock); five
            // rows read straight off the model doesn't earn a new
            // abstraction.
            'recentLogins' => AuditLogEntry::query()
                ->where('actor_type', AuditActorType::User)
                ->where('actor_id', $user->id)
                ->where('action', 'auth.login')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn (AuditLogEntry $entry) => $entry->created_at->toIso8601String())
                ->values(),
        ]);
    }
}
