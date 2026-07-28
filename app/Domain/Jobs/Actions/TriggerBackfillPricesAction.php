<?php

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Pricing\Actions\BackfillPricesAction;
use App\Domain\Pricing\DTOs\BackfillChunkProgress;
use App\Domain\Pricing\Enums\PriceSource;
use App\Models\AdminUser;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Ports the Next.js app's triggerBackfillPrices() Server Action exactly
 * (src/app/admin/(protected)/jobs/actions.ts) — the admin Jobs page's
 * "Run backfill" form. ADMIN only, enforced by the route's
 * `admin.role:ADMIN` middleware.
 *
 * Unlike the other two job triggers, this doesn't go through
 * App\Domain\Jobs\Services\JobRunTracker — it needs the JobRun row's id
 * *during* the run so BackfillPricesAction's onProgress callback can write
 * live-ish progress into `summary`, visible by refreshing /admin/jobs
 * while a multi-chunk backfill is still RUNNING. The create/update
 * lifecycle below is otherwise the same contract JobRunTracker provides.
 *
 * Returns a result rather than throwing on failure — same as v2's
 * discriminated BackfillPricesState — so the controller can render a
 * specific error message without also needing its own try/catch.
 */
class TriggerBackfillPricesAction
{
    public function __construct(
        private readonly BackfillPricesAction $backfillPricesAction,
    ) {}

    /**
     * @return array{status: 'success'|'error', result?: array<string, mixed>, message?: string}
     */
    public function execute(AdminUser $adminUser, PriceSource $source, Carbon $startDate, Carbon $endDate): array
    {
        $jobRun = JobRun::create([
            'job_name' => 'backfill-prices',
            'triggered_by' => $adminUser->id,
            'status' => JobRunStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $result = $this->backfillPricesAction->execute(
                $source,
                $startDate,
                $endDate,
                onProgress: function (BackfillChunkProgress $progress) use ($jobRun): void {
                    $jobRun->update([
                        'summary' => [
                            'chunksProcessed' => $progress->chunkIndex + 1,
                            'chunkCount' => $progress->chunkCount,
                            'lastChunk' => [
                                'start' => $progress->rangeStart->toIso8601String(),
                                'end' => $progress->rangeEnd->toIso8601String(),
                                'outcome' => $progress->outcome,
                            ],
                        ],
                    ]);
                },
            );

            $jobRun->update([
                'status' => JobRunStatus::Succeeded,
                'completed_at' => now(),
                'summary' => $result,
            ]);

            return ['status' => 'success', 'result' => $result];
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => JobRunStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
