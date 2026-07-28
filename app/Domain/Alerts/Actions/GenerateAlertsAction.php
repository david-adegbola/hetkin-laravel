<?php

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Metering\Models\MeteringPoint;
use App\Domain\Organizations\Enums\OrganizationStatus;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Scopes\OrganizationScope;

/**
 * Ports the Next.js app's generateAlertsJob() exactly (src/lib/jobs/
 * generate-alerts.ts) — DATA_GAP alert generation. The other alert types
 * are created inline where the triggering event happens (see
 * App\Domain\Optimization\Actions\RunOptimizationAction for
 * NEW_OPPORTUNITY, App\Domain\Billing\Actions\GenerateSavingsAssessmentAction
 * for REPORT_READY, App\Domain\Billing\Actions\RunVerificationAction for
 * VARIANCE_FLAG). A data gap is different: nothing "happens" to trigger
 * it, it's the passage of time without new data that matters, so it needs
 * its own periodic job. Meant to run daily.
 *
 * Cross-tenant by design (iterates every PILOT/ACTIVE organization), so
 * every query here explicitly bypasses OrganizationScope rather than
 * relying on CurrentOrganization being bound to any one org.
 */
class GenerateAlertsAction
{
    // 7 days — generous on purpose: Datahub auto-ingestion isn't wired up
    // yet, manual upload is the only path today, and a weekly cadence is a
    // reasonable ask of a pilot customer without penalizing normal upload
    // rhythm. Matches v2's DATA_GAP_THRESHOLD_MS exactly.
    private const DATA_GAP_THRESHOLD_DAYS = 7;

    public function __construct(
        private readonly CreateAlertAction $createAlertAction,
    ) {}

    /**
     * @return array{organizationsChecked: int, alertsCreated: int, results: list<array{organizationId: string, alertCreated: bool}>}
     */
    public function execute(): array
    {
        $organizations = Organization::query()
            ->whereIn('status', [OrganizationStatus::Pilot, OrganizationStatus::Active])
            ->get(['id']);

        $results = [];
        $alertsCreated = 0;

        foreach ($organizations as $organization) {
            $points = MeteringPoint::query()
                ->whereHas('site', fn ($query) => $query
                    ->withoutGlobalScope(OrganizationScope::class)
                    ->where('organization_id', $organization->id))
                ->get(['last_reading_at', 'connection_healthy']);

            // No metering point at all yet is an onboarding-stage gap,
            // already surfaced by the dashboard's onboarding checklist —
            // don't duplicate that signal here.
            if ($points->isEmpty()) {
                $results[] = ['organizationId' => $organization->id, 'alertCreated' => false];

                continue;
            }

            $mostRecentReading = $points->pluck('last_reading_at')->filter()->max();
            $stale = $mostRecentReading === null || $mostRecentReading->lt(now()->subDays(self::DATA_GAP_THRESHOLD_DAYS));
            $anyUnhealthy = $points->contains(fn (MeteringPoint $point) => ! $point->connection_healthy);

            $created = false;
            if ($stale || $anyUnhealthy) {
                $this->createAlertAction->execute(
                    $organization->id,
                    AlertType::DataGap,
                    AlertSeverity::Warning,
                    $stale ? "Your meter data hasn't updated recently" : 'A metering point needs attention',
                    $stale
                        ? 'We haven\'t received new consumption data '.
                            ($mostRecentReading ? 'since '.$mostRecentReading->format('d/m/Y') : 'yet').
                            '. Upload your latest export on the Data page to keep your savings assessment and recommendations current.'
                        : 'One of your metering points is flagged as unhealthy. Check your data connection or upload a fresh export.',
                );
                $created = true;
            }

            if ($created) {
                $alertsCreated++;
            }
            $results[] = ['organizationId' => $organization->id, 'alertCreated' => $created];
        }

        return [
            'organizationsChecked' => $organizations->count(),
            'alertsCreated' => $alertsCreated,
            'results' => $results,
        ];
    }
}
