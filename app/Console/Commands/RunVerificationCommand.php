<?php

namespace App\Console\Commands;

use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Jobs\Services\JobRunTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Sentry\State\Scope;
use Throwable;

use function Sentry\captureException;
use function Sentry\withScope;

/**
 * Ports the Next.js app's GET/POST /api/internal/run-verification route
 * handler exactly (src/app/api/internal/run-verification/route.ts), minus
 * the HTTP-auth layer — see routes/console.php's docblock. Scheduled
 * monthly on the 1st at 03:00 UTC (routes/console.php), matching
 * vercel.json's cron entry for this route exactly.
 *
 * Same job body (App\Domain\Billing\Actions\RunVerificationAction) the
 * admin's manual "Run now" trigger calls (App\Domain\Jobs\Actions\
 * TriggerRunVerificationAction) — both go through JobRunTracker, so both
 * leave a JobRun row, differing only in `triggered_by` ("cron" here, an
 * AdminUser id there).
 */
class RunVerificationCommand extends Command
{
    protected $signature = 'jobs:run-verification';

    protected $description = 'Compute VerificationRecords for the previous calendar month, across every locked baseline.';

    public function handle(JobRunTracker $jobRunTracker, RunVerificationAction $runVerificationAction): int
    {
        try {
            $jobRunTracker->track('run-verification', 'cron', fn () => $runVerificationAction->execute());
        } catch (Throwable $e) {
            $this->error("[run-verification] {$e->getMessage()}");
            Log::error('[cron] run-verification run failed', ['exception' => $e]);

            // Matches v2's own route handler exactly: `Sentry.captureException(err,
            // { tags: { job: "run-verification" } })`. No-ops when SENTRY_LARAVEL_DSN
            // is unset.
            withScope(function (Scope $scope) use ($e): void {
                $scope->setTag('job', 'run-verification');
                captureException($e);
            });

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
