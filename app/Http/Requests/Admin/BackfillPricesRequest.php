<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Ports triggerBackfillPrices()'s Server Action validation exactly
 * (src/app/admin/(protected)/jobs/actions.ts) — the admin "Run backfill"
 * form's own date-range and 400-day cap checks. A range this size is
 * already ~13 months of daily chunks; anything bigger should go through
 * `php artisan prices:backfill`, which has no such ceiling.
 */
class BackfillPricesRequest extends FormRequest
{
    public const MAX_ADMIN_BACKFILL_DAYS = 400;

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
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $startDate = Carbon::parse((string) $this->input('start_date'), 'UTC')->startOfDay();
            $endDate = Carbon::parse((string) $this->input('end_date'), 'UTC')->startOfDay();
            $rangeDays = (int) round($startDate->diffInDays($endDate));

            if ($rangeDays > self::MAX_ADMIN_BACKFILL_DAYS) {
                $validator->errors()->add(
                    'end_date',
                    "That's {$rangeDays} days — the admin UI caps backfills at ".self::MAX_ADMIN_BACKFILL_DAYS.
                        '. Run "php artisan prices:backfill" locally for a larger range.',
                );
            }
        });
    }

    public function startDate(): Carbon
    {
        return Carbon::parse((string) $this->input('start_date'), 'UTC')->startOfDay();
    }

    public function endDate(): Carbon
    {
        return Carbon::parse((string) $this->input('end_date'), 'UTC')->startOfDay();
    }
}
