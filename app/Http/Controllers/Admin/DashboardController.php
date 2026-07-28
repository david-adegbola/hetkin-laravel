<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Audit\Services\AuditChainIntegrityChecker;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\Baseline;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Jobs\Enums\JobRunStatus;
use App\Domain\Jobs\Models\JobRun;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's admin root, src/app/admin/(protected)/page.tsx
 * (`AdminOverviewPage`) — a read-only system-health snapshot, no role gate.
 * Every section is now ported in full, including the unread-Alert count
 * (Stage 10) — global across every organization, matching v2's
 * `db.alert.count({ where: { readAt: null } })` exactly (not org-scoped,
 * unlike the portal dashboard's own count).
 */
class DashboardController extends Controller
{
    /** Matches v2's PRICE_STALE_HOURS const exactly. */
    private const PRICE_STALE_HOURS = 26;

    public function index(AuditChainIntegrityChecker $integrityChecker): Response
    {
        $orgsByStatus = Organization::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $pendingPilots = PilotApplication::query()
            ->whereIn('status', [PilotApplicationStatus::Pending, PilotApplicationStatus::InReview])
            ->count();

        $baselinesByStatus = Baseline::withoutGlobalScope(OrganizationScope::class)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $verificationByStatus = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->selectRaw('review_status, count(*) as aggregate')
            ->groupBy('review_status')
            ->pluck('aggregate', 'review_status');

        $flaggedUnresolvedCount = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->where('variance_flagged', true)
            ->where('review_status', '!=', VerificationReviewStatus::LiveApproved)
            ->count();

        $invoiceStats = Invoice::withoutGlobalScope(OrganizationScope::class)
            ->selectRaw('status, count(*) as aggregate, sum(total_cents) as total_cents_sum')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $latestPrice = ElectricityPrice::query()->orderByDesc('hour_start')->first();
        $priceHoursStale = $latestPrice ? now()->diffInHours($latestPrice->hour_start) : null;

        $recentJobRuns = JobRun::query()->orderByDesc('started_at')->limit(20)->get();
        $lastRunPerJob = $recentJobRuns->unique('job_name')->keyBy('job_name');
        $anyJobFailed = $lastRunPerJob->contains(fn (JobRun $run) => $run->status === JobRunStatus::Failed);

        $integrity = $integrityChecker->verify();

        $activeAdminCount = AdminUser::query()->where('active', true)->count();

        $unreadAlertCount = Alert::withoutGlobalScope(OrganizationScope::class)
            ->whereNull('read_at')
            ->count();

        $priceStale = $priceHoursStale === null || $priceHoursStale > self::PRICE_STALE_HOURS;
        $verificationPending = (int) ($verificationByStatus['SHADOW_PENDING_REVIEW'] ?? 0);

        return Inertia::render('Admin/Overview', [
            'organizations' => [
                'total' => (int) $orgsByStatus->sum(),
                'by_status' => $orgsByStatus,
            ],
            'pending_pilot_applications' => $pendingPilots,
            'baselines' => [
                'locked' => (int) ($baselinesByStatus['LOCKED'] ?? 0),
                'total' => (int) $baselinesByStatus->sum(),
            ],
            'verification' => [
                'pending_review' => $verificationPending + (int) ($verificationByStatus['SHADOW_CONFIRMED'] ?? 0),
                'flagged_unresolved' => $flaggedUnresolvedCount,
            ],
            'invoices' => [
                'draft' => (int) ($invoiceStats['DRAFT']->aggregate ?? 0),
                'outstanding_cents' => (int) ($invoiceStats['ISSUED']->total_cents_sum ?? 0),
            ],
            'pricing_freshness' => [
                'hours_stale' => $priceHoursStale,
                'stale' => $priceStale,
            ],
            'jobs' => [
                'any_failed' => $anyJobFailed,
                'last_runs' => $lastRunPerJob->map(fn (JobRun $run) => [
                    'job_name' => $run->job_name,
                    'status' => $run->status->value,
                    'started_at' => $run->started_at->toIso8601String(),
                ])->values(),
            ],
            'audit_chain' => $integrity,
            'active_admin_count' => $activeAdminCount,
            'unread_alert_count' => $unreadAlertCount,
            'needs_attention' => $priceStale || $anyJobFailed || ! $integrity['valid'] || $verificationPending > 0,
        ]);
    }
}
