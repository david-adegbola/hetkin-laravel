<?php

namespace App\Http\Requests;

use App\Domain\Organizations\Enums\ProcessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ports the Next.js app's `pilotApplicationSchema` (src/lib/pilot.ts) zod
 * schema exactly, field-for-field. Public, unauthenticated route — see
 * App\Domain\Pilots\Actions\SubmitPilotApplicationAction for the
 * rate-limiting that sits in front of this.
 */
class PilotApplicationRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'min:2', 'max:200'],
            'process_type' => ['required', Rule::enum(ProcessType::class)],
            'estimated_annual_spend_eur' => ['required', 'integer', 'min:0', 'max:50000000'],
            'current_scheduling_method' => ['required', 'string', 'min:2', 'max:500'],
            'win_condition' => ['nullable', 'string', 'max:1000'],
            'contact_name' => ['required', 'string', 'min:2', 'max:200'],
            'contact_email' => ['required', 'email', 'max:320'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required',
            'estimated_annual_spend_eur.required' => 'Enter an estimated annual electricity spend',
            'contact_name.required' => 'Your name is required',
            'contact_email.email' => 'Enter a valid email address',
        ];
    }
}
