<?php

namespace App\Domain\Jobs\Actions;

use App\Domain\Billing\Actions\RunVerificationAction;
use App\Domain\Jobs\Services\JobRunTracker;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Ports the Next.js app's triggerRunVerification() Server Action exactly
 * (src/app/admin/(protected)/jobs/actions.ts) — the admin Jobs page's
 * "Run now" button for run-verification. ADMIN only, enforced by the
 * route's `admin.role:ADMIN` middleware.
 *
 * Same job body (App\Domain\Billing\Actions\RunVerificationAction) a
 * future scheduled command would call with `triggeredBy: "cron"`; this
 * manual path passes the acting admin's own id, unprefixed — matching the
 * `job_runs.triggered_by` column's "cron, or a bare AdminUser id" contract
 * (see the creating migration's comment).
 *
 * v2 swallows a failed run here rather than surfacing it as a thrown error:
 * the failure is already durably recorded on the JobRun row (App\Domain\
 * Jobs\Services\JobRunTracker writes it before re-throwing), so blowing up
 * the request on top of that would be redundant. This port does the same —
 * logs and returns rather than propagating.
 *
 * `ingest-prices` and `generate-alerts` are real, implemented jobs in v2
 * (not placeholders) but have no Laravel port yet — they depend on the
 * ENTSO-E price provider and Alert model, both out of scope until Stage
 * 10/11. Only run-verification gets a real trigger here; the admin Jobs
 * page's "honest subset" (MIGRATION_PLAN.md ADR-33's pattern) shows the
 * other two as known-but-unwired.
 */
class TriggerRunVerificationAction
{
    public function __construct(
        private readonly JobRunTracker $jobRunTracker,
        private readonly RunVerificationAction $runVerificationAction,
    ) {}

    public function execute(AdminUser $adminUser): void
    {
        try {
            $this->jobRunTracker->track(
                'run-verification',
                $adminUser->id,
                fn () => $this->runVerificationAction->execute(),
            );
        } catch (Throwable $e) {
            Log::error('[admin/jobs] manual run-verification run failed', ['exception' => $e]);
        }
    }
}
