<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ports addScheduleBlock()'s validation rules exactly (src/app/(portal)/
 * dashboard/schedule/actions.ts): asset label required, both dates
 * required and end after start, estimated kWh optional but strictly
 * positive when given.
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
        ];
    }
}
