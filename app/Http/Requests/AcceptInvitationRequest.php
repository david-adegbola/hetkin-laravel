<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Ports the shape of the Next.js app's AcceptInvitationForm.tsx fields
 * (src/app/invite/[invitationId]/AcceptInvitationForm.tsx) — email is
 * fixed to the invitation's own address (not user-editable, so not a
 * field here), name and password are the only inputs.
 *
 * Applies this app's standard Password::defaults() policy
 * unconditionally, including for the "this email already has an
 * account, sign in instead" fallback path — safe because the only way
 * any User account has ever been created in this app is through this
 * exact flow, so every existing account's password already satisfies
 * today's policy.
 */
class AcceptInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
