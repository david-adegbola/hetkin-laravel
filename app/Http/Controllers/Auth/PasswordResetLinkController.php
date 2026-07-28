<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Actions\RequestPasswordResetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\PasswordResetLinkRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Forgot password?" — request a reset link. The response is identical
 * (generic success) whether or not the email belongs to a real account;
 * see RequestPasswordResetAction's docblock for exactly where that
 * distinction is (deliberately) discarded.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function store(PasswordResetLinkRequest $request, RequestPasswordResetAction $action): RedirectResponse
    {
        $action->execute($request->string('email')->toString());

        // Never branch on the broker status here — see class docblock.
        return back()->with('status', 'If an account exists for that email, we\'ve sent a password reset link.');
    }
}
