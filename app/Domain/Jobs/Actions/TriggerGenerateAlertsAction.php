<?php

namespace App\Domain\Jobs\Actions;

use App\Domain\Alerts\Actions\GenerateAlertsAction;
use App\Domain\Jobs\Services\JobRunTracker;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ports the Next.js app's triggerGenerateAlerts() Server Action exactly
 * (src/app/admin/(protected)/jobs/actions.ts) — the admin Jobs page's
 * "Run now" button for generate-alerts. ADMIN only, enforced by the
 * route's `admin.role:ADMIN` middleware.
 *
 * Same job body (App\Domain\Alerts\Actions\GenerateAlertsAction) a future
 * scheduled command would call with `triggeredBy: "cron"`; this manual path
 * passes the acting admin's own id. Errors are swallowed here, same
 * reasoning as the other two job triggers — the failure is already
 * durably recorded on the JobRun row by JobRunTracker.
 */
class TriggerGenerateAlertsAction
{
    public function __construct(
        private readonly JobRunTracker $jobRunTracker,
        private readonly GenerateAlertsAction $generateAlertsAction,
    ) {}

    public function execute(AdminUser $adminUser): void
    {
        try {
            $this->jobRunTracker->track(
                'generate-alerts',
                $adminUser->id,
                fn () => $this->generateAlertsAction->execute(),
            );
        } catch (Throwable $e) {
            Log::error('[admin/jobs] manual generate-alerts run failed', ['exception' => $e]);
        }
    }
}
