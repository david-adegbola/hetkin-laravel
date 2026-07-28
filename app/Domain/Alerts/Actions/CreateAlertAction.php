<?php

namespace App\Domain\Alerts\Actions;

use App\Domain\Alerts\Enums\AlertSeverity;
use App\Domain\Alerts\Enums\AlertType;
use App\Domain\Alerts\Models\Alert;
use App\Domain\Organizations\Scopes\OrganizationScope;

/**
 * Ports the Next.js app's createAlert() exactly (src/lib/alerts.ts) — the
 * shared alert-creation helper behind all four alert-producing call sites
 * (App\Domain\Optimization\Actions\RunOptimizationAction's NEW_OPPORTUNITY,
 * App\Domain\Billing\Actions\GenerateSavingsAssessmentAction's
 * REPORT_READY, App\Domain\Billing\Actions\RunVerificationAction's
 * VARIANCE_FLAG, and this domain's own GenerateAlertsAction's DATA_GAP).
 *
 * Deliberately queries with `withoutGlobalScope(OrganizationScope::class)`
 * unconditionally rather than relying on the caller having bound
 * CurrentOrganization — some callers have (the three portal-context
 * Actions above, which set it before calling), some haven't
 * (GenerateAlertsAction is genuinely cross-tenant, same as
 * RunVerificationAction) — so this stays correct regardless of caller
 * context, the same "explicit, not implicit" reasoning
 * MIGRATION_PLAN.md's Stage 9 ADR-52 documents for admin controllers.
 *
 * An org can keep triggering the same underlying condition repeatedly —
 * re-running optimization while iterating on a schedule, a data gap that
 * persists across several days of the generate-alerts job, a verification
 * job re-run after a late data correction. Without dedup, the alert feed
 * (customer-facing, on the portal dashboard) turns into noise fast.
 * Dedup key: same organization, same type, unread (`read_at IS NULL`),
 * created within the last 20 hours — `severity`/`title`/`body` are NOT
 * part of the key, so two differently-worded alerts of the same type
 * within the window still collapse to one. Nothing in this app ever sets
 * `read_at` (v2 has no "mark as read" feature either), so in practice
 * this caps a persistent condition to roughly one alert per calendar day
 * (the daily cron cadence is comfortably wider than the 20h window,
 * so consecutive days' runs each still get their own alert — by design,
 * not a bug).
 *
 * Silently no-ops on a duplicate — no exception, no return value signaling
 * "skipped" — matching v2's `return;` exactly. Does not run inside its own
 * transaction, matching v2 exactly: every call site invokes this AFTER its
 * own domain-write `DB::transaction()` has already committed, using a
 * plain (non-transactional) query — an alert failing to write should
 * never roll back the actual domain write it's describing.
 */
class CreateAlertAction
{
    private const DEDUPE_WINDOW_HOURS = 20;

    public function execute(string $organizationId, AlertType $type, AlertSeverity $severity, string $title, string $body): ?Alert
    {
        $recentDuplicate = Alert::withoutGlobalScope(OrganizationScope::class)
            ->where('organization_id', $organizationId)
            ->where('type', $type)
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subHours(self::DEDUPE_WINDOW_HOURS))
            ->exists();

        if ($recentDuplicate) {
            return null;
        }

        return Alert::create([
            'organization_id' => $organizationId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
