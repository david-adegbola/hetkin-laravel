<?php

namespace App\Http\Requests\Portal;

use App\Domain\Scheduling\Enums\LabourClassification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * Ports addScheduleBlock()'s validation rules (src/app/(portal)/
 * dashboard/schedule/actions.ts): asset label required, both dates
 * required and end after start, estimated kWh optional but strictly
 * positive when given. `labour_classification` has no v2 source to
 * mirror (new product scope — see MIGRATION_PLAN.md's Labour-Aware
 * Shiftable Load Classification ADR) but is required here, not merely
 * `nullable` with a server-side fallback: the portal form always renders
 * a pre-selected value (defaulting to Labour Bound, the conservative
 * choice), so an omitted value here would only ever mean a malformed
 * request, not a legitimate "customer didn't say" case.
 */
class StoreScheduleBlockRequest extends FormRequest
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
            'asset_label' => ['required', 'string', 'max:255'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'estimated_kwh' => ['nullable', 'numeric', 'gt:0'],
            'labour_classification' => ['required', new Enum(LabourClassification::class)],
        ];
    }
}
