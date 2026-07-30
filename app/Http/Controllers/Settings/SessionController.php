<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Actions\LogoutOtherDevicesAction;
use App\Domain\Auth\Actions\RevokeSessionAction;
use App\Domain\Auth\Models\SessionRecord;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\LoggedOutAllDevices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class SessionController extends Controller
{
    /** Revoke exactly one other device/browser session. */
    public function destroy(Request $request, SessionRecord $session, RevokeSessionAction $action): RedirectResponse
    {
        Gate::authorize('revoke', $session);

        if ($session->id === $request->session()->getId()) {
            // Revoking "this" session is just logging out — send them
            // through the real logout flow (which also clears cookies/CSRF)
            // instead of silently deleting the row out from under the
            // request currently serving this response.
            return back()->withErrors(['session' => 'Use "Sign out" to end your current session.']);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();
        $action->execute($user, $session->id);

        return back()->with('status', 'session-revoked');
    }

    /** Log out every device/browser except the one making this request. */
    public function destroyOthers(Request $request, LogoutOtherDevicesAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $revoked = $action->execute($user, $request->session()->getId());

        if ($revoked > 0) {
            // The other sessions are already revoked above — a failed
            // courtesy security alert must not turn that success into a
            // 500. Same posture as every other post-hoc notification in
            // this app; see SubmitPilotApplicationAction's docblock for
            // the incident that established the pattern.
            try {
                $user->notify(new LoggedOutAllDevices);
            } catch (Throwable $e) {
                Log::error('[auth] failed to send logged-out-all-devices notification', ['exception' => $e, 'userId' => $user->id]);
            }
        }

        return back()->with('status', 'other-sessions-logged-out');
    }
}
