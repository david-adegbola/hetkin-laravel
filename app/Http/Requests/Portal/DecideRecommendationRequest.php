<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Only the basic shape is validated here (decision is one of the three
 * known values, field types are correct) — the cross-field business rules
 * (reason required for REJECTED, adjusted start + duration required for
 * PARTIAL) live in App\Domain\Optimization\Actions\DecideRecommendationAction,
 * matching exactly where v2's own decideRecommendation() enforces them
 * (src/app/(portal)/dashboard/actions.ts), not a separate validation layer.
 */
class DecideRecommendationRequest extends FormRequest
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
            'decision' => ['required', Rule::in(['ACCEPTED', 'REJECTED', 'PARTIAL'])],
            'reason' => ['nullable', 'string'],
            'adjusted_start_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
