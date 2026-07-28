<?php

namespace App\Domain\Jobs\Models;

use App\Domain\Jobs\Enums\JobRunStatus;
use Database\Factories\JobRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mirrors the Next.js app's `JobRun` Prisma model. NOT organization-scoped —
 * a job run isn't owned by any one tenant, see the creating migration.
 */
#[Fillable(['job_name', 'status', 'triggered_by', 'started_at', 'completed_at', 'summary', 'error_message'])]
class JobRun extends Model
{
    /** @use HasFactory<JobRunFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected static function newFactory(): JobRunFactory
    {
        return JobRunFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => JobRunStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'summary' => 'array',
        ];
    }
}
