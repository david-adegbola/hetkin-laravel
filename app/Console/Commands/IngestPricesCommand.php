<?php

namespace App\Console\Commands;

use App\Domain\Jobs\Services\JobRunTracker;
use App\Domain\Pricing\Actions\IngestPricesAction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ports the Next.js app's GET/POST /api/internal/ingest-prices route
 * handler exactly (src/app/api/internal/ingest-prices/route.ts), minus the
 * HTTP-auth layer — see routes/console.php's docblock for why a Laravel
 * scheduled command needs none of verifyInternalJobRequest()'s shared-secret
 * checking. Scheduled daily at 05:00 UTC (routes/console.php), matching
 * vercel.json's cron entry for this route exactly.
 *
 * Same job body (App\Domain\Pricing\Actions\IngestPricesAction) the admin's
 * manual "Run now" trigger calls (App\Domain\Jobs\Actions\
 * TriggerIngestPricesAction) — both go through JobRunTracker, so both
 * leave a JobRun row, differing only in `triggered_by` ("cron" here, an
 * AdminUser id there).
 */
class IngestPricesCommand extends Command
{
    protected $signature = 'jobs:ingest-prices';

    protected $description = "Fetch today + tomorrow's ENTSO-E day-ahead prices and upsert them.";

    public function handle(JobRunTracker $jobRunTracker, IngestPricesAction $ingestPricesAction): int
    {
        try {
            $jobRunTracker->track('ingest-prices', 'cron', fn () => $ingestPricesAction->execute());
        } catch (Throwable $e) {
            $this->error("[ingest-prices] {$e->getMessage()}");
            Log::error('[cron] ingest-prices run failed', ['exception' => $e]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
