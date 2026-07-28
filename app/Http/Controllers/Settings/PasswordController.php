<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Auth\Actions\ChangePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, ChangePasswordAction $action): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $currentSessionId = $request->session()->getId();

        $action->execute($user, $request->string('password')->toString(), $currentSessionId);

        // Session fixation prevention on any privilege-relevant change, not
        // just login — same reasoning as AuthenticatedSessionController.
        // Destroy (not just abandon) the pre-change session row: it was
        // still fully authenticated, so leaving it in the `sessions` table
        // would defeat the "invalidate other sessions" guarantee this
        // action is supposed to provide — it would otherwise linger as a
        // live, usable session ID indistinguishable from a real device in
        // the Active Sessions list.
        $request->session()->regenerate(destroy: true);

        return back()->with('status', 'password-updated');
    }
}
