<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal overview — mirrors the Next.js app's src/app/(portal)/dashboard/
 * page.tsx in spirit, not yet in full: that page's stat cards (verified
 * savings MTD/YTD, recommendation adoption rate, active alerts) and
 * "alerts" panel query VerificationRecord and Alert, neither of which
 * exists yet (Stage 7's Baseline/Savings/Verification, Stage 10's
 * background-job-driven Alerts). Rendering an approximation of that page
 * now, backed by nothing, would show numbers and panels that don't mean
 * what they claim to — worse than an honest interim page. See
 * MIGRATION_PLAN.md ADR-33.
 *
 * "Recent recommendations" IS real as of Stage 6 — Recommendation/
 * RecommendationDecision now exist — so that one panel from v2's page is
 * included; the rest stays deferred until its own data exists.
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
        ]);
    }
}
