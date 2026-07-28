<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Jobs\Actions\TriggerBackfillPricesAction;
use App\Domain\Jobs\Actions\TriggerGenerateAlertsAction;
use App\Domain\Jobs\Actions\TriggerIngestPricesAction;
use App\Domain\Jobs\Actions\TriggerRunVerificationAction;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Pricing\Enums\PriceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BackfillPricesRequest;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/jobs/
 * {page.tsx,actions.ts,BackfillPricesForm.tsx}. All three known jobs are
 * wired to real "Run now" triggers, plus the fourth real v2 job,
 * `backfill-prices`, with its own live-progress form (App\Domain\Jobs\
 * Actions\TriggerBackfillPricesAction — unlike the other three, it
 * bypasses App\Domain\Jobs\Services\JobRunTracker so its onProgress
 * callback can update the JobRun row while the run is still in flight).
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
                    'triggerable' => true,
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

    public function triggerIngestPrices(TriggerIngestPricesAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($admin);

        return back();
    }

    public function triggerGenerateAlerts(TriggerGenerateAlertsAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($admin);

        return back();
    }

    public function triggerBackfillPrices(BackfillPricesRequest $request, TriggerBackfillPricesAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $outcome = $action->execute($admin, PriceSource::Entsoe, $request->startDate(), $request->endDate());

        if ($outcome['status'] === 'error') {
            return back()->with('backfill_result', [
                'status' => 'error',
                'message' => "Backfill failed: {$outcome['message']}",
            ]);
        }

        $result = $outcome['result'];
        $message = $result['chunksFailed'] > 0
            ? "Done with {$result['chunksFailed']} failed chunk(s) — wrote ".number_format($result['rowsWritten']).
                " rows, skipped {$result['chunksSkipped']} already-covered chunk(s). See job run detail for the failed ranges."
            : 'Wrote '.number_format($result['rowsWritten'])." rows across {$result['chunksWritten']} chunk(s), ".
                "skipped {$result['chunksSkipped']} already-covered chunk(s).";

        return back()->with('backfill_result', ['status' => 'success', 'message' => $message]);
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
