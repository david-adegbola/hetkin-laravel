<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\NewPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reset-password form itself — reached via the token-based link
 * emailed by PasswordResetRequested (Laravel's Password Broker owns the
 * token's generation/expiry/validation, not this controller).
 *
 * Per the brief: on success, redirect to Login with a success notification.
 * Does NOT log the user in — ResetPasswordAction already invalidated every
 * existing session, and auto-login here would silently undo that.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function store(NewPasswordRequest $request, ResetPasswordAction $action): RedirectResponse
    {
        $action->execute(
            $request->string('token')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
