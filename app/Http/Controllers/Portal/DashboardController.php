<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal overview — mirrors the Next.js app's src/app/(portal)/dashboard/
 * page.tsx in spirit, not yet in full: that page's OTHER three stat cards
 * (verified savings MTD/YTD, recommendation adoption rate) query
 * VerificationRecord aggregates this port hasn't built a view for yet —
 * a distinct gap from Alerts, not blocked by anything Stage 10 builds.
 * Rendering an approximation now, backed by nothing, would show numbers
 * that don't mean what they claim to — worse than an honest interim page.
 * See MIGRATION_PLAN.md ADR-33.
 *
 * "Recent recommendations" (Stage 6) and "Active alerts"/"Alerts" (Stage
 * 10) ARE real and ported in full — the rest stays deferred until its own
 * data exists.
 */
class DashboardController extends Controller
{
    public function show(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();

        $recentRecommendations = Recommendation::query()
            ->whereHas('optimizationRun', fn ($query) => $query->where('organization_id', $organization->id))
            ->with('decision')
            ->latest('created_at')
            ->take(3)
            ->get();

        $activeAlertCount = Alert::query()->whereNull('read_at')->count();

        $recentAlerts = Alert::query()->latest('created_at')->take(4)->get();

        return Inertia::render('Portal/Dashboard', [
            'recentRecommendations' => $recentRecommendations->map(fn (Recommendation $recommendation) => [
                'id' => $recommendation->id,
                'rationale' => $recommendation->rationale,
                'delivery_risk' => $recommendation->delivery_risk->value,
                'projected_savings_cents' => $recommendation->projected_savings_cents,
                'proposed_start_at' => $recommendation->proposed_start_at->toIso8601String(),
                'proposed_end_at' => $recommendation->proposed_end_at->toIso8601String(),
                'decision' => $recommendation->decision ? [
                    'decision' => $recommendation->decision->decision->value,
                    'reason' => $recommendation->decision->reason,
                    'adjusted_start_at' => $recommendation->decision->adjusted_start_at?->toIso8601String(),
                ] : null,
            ]),
            'activeAlertCount' => $activeAlertCount,
            'recentAlerts' => $recentAlerts->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type->value,
                'severity' => $alert->severity->value,
                'title' => $alert->title,
                'body' => $alert->body,
                'created_at' => $alert->created_at->toIso8601String(),
            ]),
        ]);
    }
}
