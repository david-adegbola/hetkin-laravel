<?php

namespace App\Console\Commands;

use App\Domain\Alerts\Actions\GenerateAlertsAction;
use App\Domain\Jobs\Services\JobRunTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ports the Next.js app's GET/POST /api/internal/generate-alerts route
 * handler exactly (src/app/api/internal/generate-alerts/route.ts), minus
 * the HTTP-auth layer — see routes/console.php's docblock. Scheduled
 * daily at 06:00 UTC (routes/console.php), matching vercel.json's cron
 * entry for this route exactly.
 *
 * Same job body (App\Domain\Alerts\Actions\GenerateAlertsAction) the
 * admin's manual "Run now" trigger calls (App\Domain\Jobs\Actions\
 * TriggerGenerateAlertsAction) — both go through JobRunTracker, so both
 * leave a JobRun row, differing only in `triggered_by` ("cron" here, an
 * AdminUser id there).
 */
class GenerateAlertsCommand extends Command
{
    protected $signature = 'jobs:generate-alerts';

    protected $description = 'Flag orgs whose meter data has gone stale or a metering point is unhealthy.';

    public function handle(JobRunTracker $jobRunTracker, GenerateAlertsAction $generateAlertsAction): int
    {
        try {
            $jobRunTracker->track('generate-alerts', 'cron', fn () => $generateAlertsAction->execute());
        } catch (Throwable $e) {
            $this->error("[generate-alerts] {$e->getMessage()}");
            Log::error('[cron] generate-alerts run failed', ['exception' => $e]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
