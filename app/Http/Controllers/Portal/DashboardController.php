<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Alerts\Models\Alert;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Metering\Models\MeterReading;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Optimization\Models\RecommendationDecision;
use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Pricing\Enums\BiddingZone;
use App\Domain\Pricing\Enums\PriceSource;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ScheduleBlock;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal overview — mirrors the Next.js app's src/app/(portal)/dashboard/
 * page.tsx in full as of MIGRATION_PLAN.md's post-Stage-12 ADR. The three
 * stat cards this controller used to defer (verified savings MTD/YTD,
 * recommendation adoption rate) needed VerificationRecord/RecommendationDecision
 * aggregates this port hadn't built views for at Stage 5 — all three now
 * exist (Stages 6-7), so the gap ADR-33 flagged is closed.
 *
 * v2's onboarding-status check is wrapped in `unstable_cache` there purely
 * to skip repeating three cheap existence queries plus an RLS
 * `set_config` round trip on every load, forever — see v2's
 * src/lib/onboarding-status.ts docblock. Neither cost applies to this
 * port (tenant scoping here is a plain Eloquent global scope, not a
 * per-request RLS transaction), so this runs the three `exists()` checks
 * directly with no caching layer to port.
 */
class DashboardController extends Controller
{
    public function show(Request $request, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        $scheduleTimezone = config('scheduling.timezone');
        $now = Carbon::now($scheduleTimezone);
        $monthStart = $now->clone()->startOfMonth();
        $yearStart = $now->clone()->startOfYear();
        $today = $now->clone()->startOfDay();
        $dateOptions = collect(range(-2, 1))->map(function (int $offset) use ($today): array {
            $date = $today->copy()->addDays($offset);
            $relativeLabel = match ($offset) {
                -2 => $date->format('l'),
                -1 => 'Yesterday',
                0 => 'Today',
                1 => 'Tomorrow',
            };

            return [
                'date' => $date->toDateString(),
                'label' => sprintf('%s — %s', $relativeLabel, $date->format('j F Y')),
            ];
        });
        $requestedDate = $request->string('recommendation_date')->toString();
        $selectedDate = $dateOptions->firstWhere('date', $requestedDate)
            ?? $dateOptions->firstWhere('date', $today->toDateString());
        $selectedRecommendationDay = Carbon::parse($selectedDate['date'], $scheduleTimezone)->startOfDay();
        $selectedRecommendationDayStart = $selectedRecommendationDay->copy()->utc();
        $selectedRecommendationDayEnd = $selectedRecommendationDay->copy()->addDay()->utc();
        $todayStart = $today->copy()->utc();
        $todayEnd = $today->copy()->addDay()->utc();

        $mtdSavingsCents = (int) VerificationRecord::query()
            ->where('organization_id', $organization->id)
            ->where('review_status', VerificationReviewStatus::LiveApproved)
            ->where('period_start', '>=', $monthStart)
            ->sum('verified_savings_cents');

        $ytdSavingsCents = (int) VerificationRecord::query()
            ->where('organization_id', $organization->id)
            ->where('review_status', VerificationReviewStatus::LiveApproved)
            ->where('period_start', '>=', $yearStart)
            ->sum('verified_savings_cents');

        // Full and partial adoption both count as "followed" (mirrors v2's
        // own comment: a scheduler who partially adopts is still acting on
        // the recommendation, just not exactly as proposed — a materially
        // different signal from an outright reject-with-reason).
        $decisions = RecommendationDecision::query()
            ->whereHas('recommendation.optimizationRun', fn ($query) => $query->where('organization_id', $organization->id))
            ->pluck('decision');
        $followedCount = $decisions->filter(fn (RecommendationDecisionType $d) => $d === RecommendationDecisionType::Accepted || $d === RecommendationDecisionType::Partial)->count();
        $adoptionRate = $decisions->isNotEmpty() ? (int) round(($followedCount / $decisions->count()) * 100) : null;

        $recentRecommendations = Recommendation::query()
            ->whereHas('optimizationRun', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereHas('scheduleBlock', fn ($query) => $query
                ->where('start_at', '>=', $selectedRecommendationDayStart)
                ->where('start_at', '<', $selectedRecommendationDayEnd))
            ->with(['decision', 'scheduleBlock'])
            ->orderBy('projected_savings_cents', 'desc')
            ->take(3)
            ->get();

        $selectedDayPrices = ElectricityPrice::query()
            ->where('bidding_zone', BiddingZone::FI)
            ->where('source', PriceSource::Entsoe)
            ->where('hour_start', '>=', $selectedRecommendationDayStart)
            ->where('hour_start', '<', $selectedRecommendationDayEnd)
            ->orderBy('hour_start')
            ->get();

        $todayPrices = $selectedRecommendationDay->isSameDay($today)
            ? $selectedDayPrices
            : ElectricityPrice::query()
                ->where('bidding_zone', BiddingZone::FI)
                ->where('source', PriceSource::Entsoe)
                ->where('hour_start', '>=', $todayStart)
                ->where('hour_start', '<', $todayEnd)
                ->orderBy('hour_start')
                ->get();

        $activeAlertCount = Alert::query()->whereNull('read_at')->count();

        $recentAlerts = Alert::query()->latest('created_at')->take(4)->get();

        $hasMeterData = MeterReading::query()
            ->whereHas('meteringPoint.site', fn ($query) => $query->where('organization_id', $organization->id))
            ->exists();

        $hasAssessment = SavingsAssessment::query()
            ->where('organization_id', $organization->id)
            ->exists();

        $hasScheduleBlock = ScheduleBlock::query()
            ->whereHas('schedule', fn ($query) => $query->where('organization_id', $organization->id))
            ->exists();

        return Inertia::render('Portal/Dashboard', [
            'mtdSavingsCents' => $mtdSavingsCents,
            'ytdSavingsCents' => $ytdSavingsCents,
            'adoptionRate' => $adoptionRate,
            'followedCount' => $followedCount,
            'totalDecisions' => $decisions->count(),
            'recentRecommendations' => $recentRecommendations->map(function (Recommendation $recommendation) use ($selectedDayPrices): array {
                $block = $recommendation->scheduleBlock;
                $pricePoints = $block ? $selectedDayPrices : collect();
                $averagePrice = static function (Carbon $start, Carbon $end) use ($pricePoints): ?float {
                    $window = $pricePoints->filter(fn (ElectricityPrice $price) => $price->hour_start->gte($start) && $price->hour_start->lt($end));

                    return $window->isEmpty() ? null : round((float) $window->avg('eur_per_mwh'), 2);
                };

                return [
                    'id' => $recommendation->id,
                    'schedule_block_id' => $recommendation->schedule_block_id,
                    'asset_label' => $block?->asset_label ?? 'Production block',
                    'rationale' => $recommendation->rationale,
                    'delivery_risk' => $recommendation->delivery_risk->value,
                    'projected_savings_cents' => $recommendation->projected_savings_cents,
                    'original_start_at' => $block?->start_at?->toIso8601String(),
                    'original_end_at' => $block?->end_at?->toIso8601String(),
                    'proposed_start_at' => $recommendation->proposed_start_at->toIso8601String(),
                    'proposed_end_at' => $recommendation->proposed_end_at->toIso8601String(),
                    'estimated_kwh' => $block?->estimated_kwh !== null ? (float) $block->estimated_kwh : null,
                    'original_average_eur_per_mwh' => $block ? $averagePrice($block->start_at, $block->end_at) : null,
                    'proposed_average_eur_per_mwh' => $averagePrice($recommendation->proposed_start_at, $recommendation->proposed_end_at),
                    'price_curve' => $pricePoints->map(fn (ElectricityPrice $price) => [
                        'hour_start' => $price->hour_start->toIso8601String(),
                        'eur_per_mwh' => (float) $price->eur_per_mwh,
                    ])->values(),
                    'decision' => $recommendation->decision ? [
                        'decision' => $recommendation->decision->decision->value,
                        'reason' => $recommendation->decision->reason,
                        'adjusted_start_at' => $recommendation->decision->adjusted_start_at?->toIso8601String(),
                    ] : null,
                ];
            }),
            'chartDateLabel' => sprintf('Today — %s', $today->format('j F Y')),
            'chartTimezone' => $scheduleTimezone,
            'dayAheadPrices' => $todayPrices->map(fn (ElectricityPrice $price) => [
                'hour_start' => $price->hour_start->toIso8601String(),
                'eur_per_mwh' => (float) $price->eur_per_mwh,
            ])->values(),
            'recommendationDate' => $selectedDate['date'],
            'recommendationDateLabel' => $selectedDate['label'],
            'recommendationDateOptions' => $dateOptions->values(),
            'activeAlertCount' => $activeAlertCount,
            'recentAlerts' => $recentAlerts->map(fn (Alert $alert) => [
                'id' => $alert->id,
                'type' => $alert->type->value,
                'severity' => $alert->severity->value,
                'title' => $alert->title,
                'body' => $alert->body,
                'created_at' => $alert->created_at->toIso8601String(),
            ]),
            'onboarding' => [
                'hasMeterData' => $hasMeterData,
                'hasAssessment' => $hasAssessment,
                'hasScheduleBlock' => $hasScheduleBlock,
                'hasDecision' => $decisions->isNotEmpty(),
            ],
        ]);
    }
}
