<?php

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Services\JobRunTracker;
use App\Domain\Pricing\Actions\IngestPricesAction;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ports the Next.js app's triggerIngestPrices() Server Action exactly
 * (src/app/admin/(protected)/jobs/actions.ts) — the admin Jobs page's
 * "Run now" button for ingest-prices. ADMIN only, enforced by the route's
 * `admin.role:ADMIN` middleware.
 *
 * Same job body (App\Domain\Pricing\Actions\IngestPricesAction) a future
 * scheduled command would call with `triggeredBy: "cron"`; this manual path
 * passes the acting admin's own id. Errors are swallowed here, same
 * reasoning as App\Domain\Jobs\Actions\TriggerRunVerificationAction — the
 * failure is already durably recorded on the JobRun row by JobRunTracker.
 */
class TriggerIngestPricesAction
{
    public function __construct(
        private readonly JobRunTracker $jobRunTracker,
        private readonly IngestPricesAction $ingestPricesAction,
    ) {}

    public function execute(AdminUser $adminUser): void
    {
        try {
            $this->jobRunTracker->track(
                'ingest-prices',
                $adminUser->id,
                fn () => $this->ingestPricesAction->execute(),
            );
        } catch (Throwable $e) {
            Log::error('[admin/jobs] manual ingest-prices run failed', ['exception' => $e]);
        }
    }
}
