<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Jobs\Actions\TriggerRunVerificationAction;
use App\Domain\Jobs\Models\JobRun;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/jobs/
 * {page.tsx,actions.ts}'s three job cards, minus `ingest-prices` and
 * `generate-alerts` — both are real, implemented jobs in v2, but their
 * Laravel job bodies depend on work this port hasn't reached yet (the
 * ENTSO-E price provider, the Alert model — Stage 10/11). Only
 * `run-verification` gets a real "Run now" trigger here; the other two are
 * shown as known-but-unwired cards, MIGRATION_PLAN.md ADR-33's "honest
 * subset" pattern applied to this page. `backfill-prices` (a fourth,
 * real v2 job with its own live-progress form) is out of scope for the
 * same reason as `ingest-prices`.
 *
 * The index has no role gate; triggering is `admin.role:ADMIN` only,
 * matching v2's `requireAdminRole(["ADMIN"])` on every trigger action.
 */
class JobController extends Controller
{
    /**
     * @var list<string>
     */
    private const KNOWN_JOBS = ['ingest-prices', 'run-verification', 'generate-alerts'];

    public function index(): Response
    {
        $lastRuns = JobRun::query()
            ->whereIn('job_name', self::KNOWN_JOBS)
            ->orderByDesc('started_at')
            ->get()
            ->unique('job_name')
            ->keyBy('job_name');

        $recentRuns = JobRun::query()->orderByDesc('started_at')->limit(50)->get();

        return Inertia::render('Admin/Jobs', [
            'known_jobs' => collect(self::KNOWN_JOBS)->map(function (string $jobName) use ($lastRuns) {
                $lastRun = $lastRuns->get($jobName);

                return [
                    'job_name' => $jobName,
                    'triggerable' => $jobName === 'run-verification',
                    'last_run' => $lastRun ? $this->serializeRun($lastRun) : null,
                ];
            }),
            'recent_runs' => $recentRuns->map(fn (JobRun $run) => $this->serializeRun($run)),
        ]);
    }

    public function triggerRunVerification(TriggerRunVerificationAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($admin);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRun(JobRun $run): array
    {
        return [
            'id' => $run->id,
            'job_name' => $run->job_name,
            'status' => $run->status->value,
            'triggered_by' => $run->triggered_by,
            'started_at' => $run->started_at->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'summary' => $run->summary,
            'error_message' => $run->error_message,
        ];
    }
}
