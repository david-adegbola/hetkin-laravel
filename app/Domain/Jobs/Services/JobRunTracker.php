<?php

namespace App\Domain\Jobs\Services;

use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use Closure;
use Throwable;

/**
 * Ports the Next.js app's withJobRunTracking() exactly (src/lib/jobs/
 * job-run-tracker.ts) — every job body in this app (currently only
 * App\Domain\Billing\Actions\RunVerificationAction is wired to a trigger;
 * see App\Domain\Jobs\Actions\TriggerRunVerificationAction) runs through
 * this wrapper, whether the eventual caller is a scheduled command
 * ("cron") or an admin's manual "Run now" (the admin's own id).
 *
 * Creates the JobRun row RUNNING before `$fn` runs, then updates the SAME
 * row to SUCCEEDED with `$fn`'s return value as `summary` (stored verbatim,
 * whatever shape the job body returns) or to FAILED with the exception's
 * message as `error_message`. Always re-throws on failure — same as v2's
 * `throw err` — so the failure is durably recorded here AND still visible
 * to whatever's calling `track()`; callers decide whether to propagate or
 * swallow it further up (v2's cron route re-throws all the way to a 500;
 * its admin manual-trigger action catches and logs instead — this port's
 * two call sites make the same choice at that same layer, not here).
 */
class JobRunTracker
{
    /**
     * @template T
     *
     * @param  Closure(): T  $fn
     *
     * @throws Throwable
     */
    public function track(string $jobName, string $triggeredBy, Closure $fn): JobRun
    {
        $jobRun = JobRun::create([
            'job_name' => $jobName,
            'triggered_by' => $triggeredBy,
            'status' => JobRunStatus::Running,
            // Explicit, not left to the migration's `useCurrent()`
            // default — that default is evaluated by Postgres itself,
            // while `completed_at` below always comes from PHP's own
            // `now()`. A live smoke test caught the two disagreeing by
            // ~1s even after pinning the connection's session timezone to
            // UTC (config/database.php) — ordinary clock drift between
            // the DB process and the app process, not a timezone bug, but
            // avoidable entirely by sourcing both timestamps from the same
            // clock, so a job's duration can never come out negative.
            'started_at' => now(),
        ]);

        try {
            $result = $fn();

            $jobRun->update([
                'status' => JobRunStatus::Succeeded,
                'completed_at' => now(),
                'summary' => $result,
            ]);
        } catch (Throwable $e) {
            $jobRun->update([
                'status' => JobRunStatus::Failed,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $jobRun;
    }
}
