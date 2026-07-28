<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal overview — mirrors the Next.js app's src/app/(portal)/dashboard/
 * page.tsx in spirit, not yet in full: that page's stat cards (verified
 * savings MTD/YTD, recommendation adoption rate, active alerts) and
 * "recent recommendations"/"alerts" panels all query VerificationRecord,
 * RecommendationDecision, Recommendation, and Alert — none of which exist
 * yet (Stage 6's Optimization/Recommendations, Stage 7's Baseline/Savings/
 * Verification, Stage 10's background-job-driven Alerts). Rendering an
 * approximation of that page now, backed by nothing, would show numbers
 * and panels that don't mean what they claim to — worse than an honest
 * interim page. See MIGRATION_PLAN.md ADR-33.
 *
 * What Stage 5 actually unlocks (Schedule, Data) is real, and linked from
 * here and the portal nav; the full stat-card overview lands once its
 * data sources exist.
 */
class DashboardController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Portal/Dashboard');
    }
}
