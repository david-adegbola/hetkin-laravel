<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ports uploadMeterCsv()'s HTTP-shaped checks (src/app/(portal)/dashboard/
 * data/upload/actions.ts) — file present, <= 10MB. v2 never validated the
 * file's MIME/extension at this layer either (real Fingrid exports have
 * inconsistent content-types across browsers/OSes) — content-format
 * validation is FingridCsvParser's job, not this Request's, matching
 * exactly.
 */
class StoreMeterUploadRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:10240'],
        ];
    }
}
