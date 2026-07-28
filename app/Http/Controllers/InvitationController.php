<?php

namespace App\Http\Controllers;

use App\Domain\Organizations\Actions\AcceptInvitationAction;
use App\Domain\Organizations\Exceptions\InvitationException;
use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/invite/[invitationId]/{page.tsx,
 * AcceptInvitationForm.tsx, actions.ts}. Public, unauthenticated route —
 * the invitee isn't a member of the target org yet (that's what accepting
 * grants), so every lookup here explicitly bypasses OrganizationScope,
 * the same way v2's adminScopedDb() does for the identical reason.
 *
 * Combines v2's two-step client/server split (browser calls Better Auth's
 * signUp/signIn client SDK directly so its cookie-setting fetch behaves
 * like a real browser request, THEN a separate Server Action grants
 * membership) into one server-side POST — Laravel sets the session cookie
 * on a normal form submission natively, so there's no equivalent reason to
 * split it. Same customer-visible contract (submit name + password, land
 * in the new org), different mechanism — see MIGRATION_PLAN.md's Stage 8
 * ADR (same reasoning as ADR-44's dompdf swap).
 */
class InvitationController extends Controller
{
    public function show(string $invitation): Response
    {
        /** @var Invitation|null $model */
        $model = Invitation::withoutGlobalScope(OrganizationScope::class)
            ->with('organization')
            ->find($invitation);

        return Inertia::render('Invite/Accept', [
            'invitation' => $model ? [
                'id' => $model->id,
                'email' => $model->email,
                'organization_name' => $model->organization->name,
            ] : null,
            'invalid_reason' => $this->invalidReason($model),
        ]);
    }

    public function store(AcceptInvitationRequest $request, string $invitation, AcceptInvitationAction $action): RedirectResponse
    {
        /** @var Invitation|null $model */
        $model = Invitation::withoutGlobalScope(OrganizationScope::class)->find($invitation);
        abort_if($model === null, 404);

        $validated = $request->validated();

        // Try account creation first (most common path: first time seeing
        // this invite). If an account with this email already exists (a
        // second invitation, or they signed up separately), fall back to
        // signing in with the password they just entered rather than
        // dead-ending — mirrors AcceptInvitationForm.tsx's signUp-then-
        // signIn-fallback exactly.
        $user = User::where('email', $model->email)->first();

        if ($user) {
            if (! Hash::check($validated['password'], $user->password)) {
                return back()->withErrors(['password' => 'Could not create your account.']);
            }
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $model->email,
                'password' => $validated['password'],
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        try {
            $action->execute($model, $user);
        } catch (InvitationException $e) {
            // Surfaced next to the password field — there's no dedicated
            // "invitation" input for it to attach to, and this is the
            // field closest to the submit button. See resources/js/Pages/
            // Invite/Accept.tsx.
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        $target = $request->getHost() === 'dashboard.'.config('app.domain')
            ? route('portal.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        return redirect($target);
    }

    private function invalidReason(?Invitation $invitation): ?string
    {
        if ($invitation === null) {
            return "This invitation link isn't valid.";
        }
        if ($invitation->status !== 'pending') {
            return 'This invitation has already been used.';
        }
        if ($invitation->expires_at->isPast()) {
            return 'This invitation has expired. Ask your Hetkin contact to resend it.';
        }

        return null;
    }
}
