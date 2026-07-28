<?php

namespace App\Domain\Billing\Reports;

use App\Domain\Metering\Models\MeterReading;
use App\Domain\Optimization\Models\Recommendation;
use App\Domain\Pricing\Models\ElectricityPrice;
use App\Domain\Scheduling\Models\ScheduleBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ports the Next.js app's buildSavingsReportViewModel() exactly
 * (src/lib/reports/savings-report.ts) — a pure function of
 * SavingsReportData, consumed identically by the web report page and the
 * PDF renderer so the two can never drift out of sync.
 *
 * Returns a plain nested array (snake_case keys, matching every other
 * Inertia prop payload in this codebase) rather than a hierarchy of ~15
 * DTO classes — both Inertia props and the Blade PDF view consume plain
 * arrays natively, and nothing statically checks either against a PHP
 * type, so a class hierarchy would only add indirection between here and
 * the render boundary, not safety.
 */
class SavingsReportViewModelBuilder
{
    private const GLOSSARY = [
        ['ICP score', 'Ideal Customer Profile fit score (0–100), used to triage pilot applications — not a gate.'],
        ['Baseline', 'The reference annual electricity cost this assessment compares an optimized schedule against.'],
        ['Provisional', "Not yet through Hetkin's methodology/legal review — treat the figure as an estimate, not final."],
        ['Locked baseline', 'A baseline whose cost/assumption fields are frozen and used for ongoing monthly verification.'],
        ['Bidding zone', 'The regional electricity market Hetkin sources day-ahead prices for (FI = Finland).'],
        ['Day-ahead price', 'The spot price for electricity delivered the following day, set by the Nord Pool/ENTSO-E auction.'],
        ['Net customer savings', "Estimated annual savings after Hetkin's revenue-share fee is deducted."],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function build(SavingsReportData $data): array
    {
        $assessment = $data->assessment;
        $organization = $data->organization;
        $baseline = $data->baseline;

        $periodLabel = self::formatDateRange($assessment->period_start, $assessment->period_end);
        $generatedLabel = $assessment->generated_at->format('d/m/Y')." · Methodology {$baseline->methodology_version}";

        $avgLoadProfile = self::buildAvgLoadProfile($data->readings);
        [$heatmap, $trend, $minPrice, $maxPrice] = self::buildMarket($data->prices);
        $peakCostHours = self::peakCostHours($heatmap);

        $waterfall = [
            ['label' => 'Total projected savings', 'delta_cents' => $assessment->estimated_annual_savings_cents, 'cumulative_cents' => $assessment->estimated_annual_savings_cents, 'kind' => 'start'],
            ['label' => "Hetkin's service fee", 'delta_cents' => -$assessment->hetkin_share_cents, 'cumulative_cents' => $assessment->estimated_annual_savings_cents - $assessment->hetkin_share_cents, 'kind' => 'decrease'],
            ['label' => 'Net customer savings', 'delta_cents' => 0, 'cumulative_cents' => $assessment->net_customer_savings_cents, 'kind' => 'end'],
        ];

        $savingsToFeeRatio = $assessment->hetkin_share_cents > 0
            ? $assessment->net_customer_savings_cents / $assessment->hetkin_share_cents
            : null;
        $savingsPercent = $assessment->current_annual_cost_cents > 0
            ? $assessment->estimated_annual_savings_cents / $assessment->current_annual_cost_cents * 100
            : 0.0;

        $current = $data->scheduleBlocks->map(fn (ScheduleBlock $b) => self::toBar($b->start_at, $b->end_at, $b->asset_label, $heatmap))->values()->all();
        $recommendedBars = $data->recommendations->map(fn (Recommendation $r) => self::toBar(
            $r->proposed_start_at, $r->proposed_end_at, $r->scheduleBlock?->asset_label ?? 'Proposed block', $heatmap,
        ))->values()->all();

        $calloutLines = $data->recommendations->take(4)->map(function (Recommendation $r) {
            $asset = $r->scheduleBlock?->asset_label ?? 'This block';

            return "{$asset} — {$r->rationale} (".Money::formatEurCents($r->projected_savings_cents).'/period)';
        })->values()->all();

        $recommendationRows = $data->recommendations->map(fn (Recommendation $r) => [
            'title' => ($r->scheduleBlock?->asset_label ?? 'Proposed change').': '
                .$r->proposed_start_at->copy()->utc()->format('H:i').'–'.$r->proposed_end_at->copy()->utc()->format('H:i'),
            'rationale' => $r->rationale,
            'savings_label' => Money::formatEurCents($r->projected_savings_cents),
            'complexity' => $r->delivery_risk->value,
            'business_impact' => $r->business_impact ?? 'Not specified',
        ])->values()->all();

        $assumptions = collect($baseline->assumptions ?? [])
            ->reject(fn ($v, $k) => $k === 'note')
            ->map(fn ($v, $k) => [self::titleCase($k), self::formatAssumptionValue($v)])
            ->values()->all();
        $exclusions = [
            'Grid connection fees and taxes, unless explicitly listed in Assumptions below',
            'Equipment ramp-up/ramp-down energy draw not separately metered',
            "Demand (capacity) charges, if applicable to this customer's electricity contract",
            'Non-electricity operating costs',
        ];
        $coveredHours = $data->readings->count();
        $expectedHours = ($assessment->period_start && $assessment->period_end)
            ? (int) round($assessment->period_start->diffInSeconds($assessment->period_end) / 3600) + 1
            : null;

        $isProvisional = $assessment->is_provisional;
        $kpis = [
            ['label' => 'Annual savings', 'value' => Money::formatEurCents($assessment->estimated_annual_savings_cents), 'tone' => 'cheap'],
            ['label' => 'Monthly savings', 'value' => Money::formatEurCents((int) round($assessment->estimated_annual_savings_cents / 12)), 'tone' => 'cheap'],
            ['label' => 'Avg. cost reduction', 'value' => number_format($savingsPercent, 1).'%', 'tone' => 'cheap'],
            ['label' => 'Net after Hetkin fee', 'value' => $savingsToFeeRatio !== null ? number_format($savingsToFeeRatio, 1).'x ROI' : 'n/a', 'tone' => 'neutral'],
        ];

        $processTypeLabel = $organization->process_type?->label() ?? 'Not set';

        return [
            'cover' => [
                'organization_name' => $organization->name,
                'report_title' => 'Savings Assessment Report',
                'period_label' => $periodLabel,
                'generated_label' => $generatedLabel,
                'version' => $data->versionNumber,
                'assessment_status' => $assessment->status->value,
                'is_provisional' => $isProvisional,
                'baseline_status' => $baseline->status->value,
            ],
            'executive_summary' => [
                'situation' => "{$organization->name}'s production schedule currently runs against the electricity spot market rather than with it.",
                'kpis' => $kpis,
                'waterfall_thumbnail' => $waterfall,
                'key_recommendations' => collect($recommendationRows)->take(3)->pluck('title')->values()->all(),
                'roi_label' => $savingsToFeeRatio !== null
                    ? number_format($savingsToFeeRatio, 1)."x — every €1 of Hetkin's fee returns ".number_format($savingsToFeeRatio, 1).'x in net customer savings'
                    : 'Fee not yet configured',
                'status_note' => $isProvisional
                    ? 'This is a provisional estimate based on '.($assessment->sample_day_count ?? 'an unspecified number of').' days of metered data. It has not yet been through Hetkin\'s methodology review — see Methodology, below.'
                    : null,
                'next_steps' => [
                    'Review the recommended schedule changes with production leadership (see Production Schedule Optimization).',
                    'Approve and lock the baseline to begin monthly verification.',
                    'Contact Hetkin to discuss implementation support for the highest-savings recommendations.',
                ],
            ],
            'customer_overview' => [
                'company_name' => $organization->name,
                'process_type_label' => $processTypeLabel,
                'period_label' => $periodLabel,
                'metering_points' => $data->meteringPoints->map(fn ($mp) => [
                    'label' => $mp->label,
                    'site_name' => $mp->site->name,
                    'data_source' => $mp->data_source->value,
                    'healthy' => $mp->connection_healthy,
                ])->values()->all(),
                'data_coverage' => [
                    'day_count' => $assessment->sample_day_count,
                    'hour_count' => $coveredHours,
                    'expected_hour_count' => $expectedHours,
                ],
                // No pilot-funnel domain yet (Stage 8) — see this class's docblock.
                'production_profile' => 'Not provided.',
            ],
            'baseline' => [
                'current_annual_cost_label' => Money::formatEurCents($assessment->current_annual_cost_cents),
                'avg_load_profile' => $avgLoadProfile,
                'peak_cost_hours' => $peakCostHours,
                'cost_driver_note' => count($peakCostHours) > 0
                    ? 'Consumption during hours '.implode(', ', $peakCostHours).":00 overlaps with this period's most expensive electricity — the primary driver of avoidable cost."
                    : 'Not enough matched price data to identify specific cost-driving hours.',
            ],
            'market' => [
                'heatmap' => $heatmap,
                'min_price_label' => $minPrice !== null ? '€'.number_format($minPrice, 0).'/MWh' : 'n/a',
                'max_price_label' => $maxPrice !== null ? '€'.number_format($maxPrice, 0).'/MWh' : 'n/a',
                'volatility_note' => ($minPrice !== null && $maxPrice !== null)
                    ? 'Prices ranged from €'.number_format($minPrice, 0).' to €'.number_format($maxPrice, 0).' per MWh over this period — a '.number_format($maxPrice / max($minPrice, 1), 1).'x spread between the cheapest and most expensive hour.'
                    : 'Insufficient price data for this period.',
                'trend' => $trend,
            ],
            'savings' => [
                'waterfall' => $waterfall,
                'table' => [
                    ['Current annual cost', Money::formatEurCents($assessment->current_annual_cost_cents)],
                    ['Optimized annual cost', Money::formatEurCents($assessment->optimized_annual_cost_cents)],
                    ['Total projected savings', Money::formatEurCents($assessment->estimated_annual_savings_cents)],
                    ['Savings percentage', number_format($savingsPercent, 1).'%'],
                    ["Hetkin's service fee", Money::formatEurCents($assessment->hetkin_share_cents)],
                    ['Net customer savings', Money::formatEurCents($assessment->net_customer_savings_cents)],
                ],
            ],
            'schedule' => [
                'current' => $current,
                'recommended' => $recommendedBars,
                'callouts' => count($calloutLines) > 0
                    ? $calloutLines
                    : ['No schedule recommendations available yet — run the optimization engine against an active production schedule.'],
            ],
            'recommendations' => $recommendationRows,
            'methodology' => [
                'strategy_label' => $baseline->methodology_version,
                'status_label' => $isProvisional ? 'Provisional — not yet through methodology review' : 'Reviewed',
                'sample_day_count' => $assessment->sample_day_count,
                'coverage_note' => $expectedHours !== null
                    ? number_format($coveredHours).' of '.number_format($expectedHours).' expected hourly readings ('.round($coveredHours / $expectedHours * 100).'% coverage)'
                    : number_format($coveredHours).' hourly readings',
                'price_source' => 'ENTSO-E Transparency Platform, day-ahead auction, Finland (FI) bidding zone',
                'assumptions' => $assumptions,
                'exclusions' => $exclusions,
            ],
            'appendix' => [
                'glossary' => self::GLOSSARY,
                'version_history' => "This is version {$data->versionNumber} of the savings assessment generated for {$organization->name}.",
                'contact_email' => 'hello@hetkin.app',
            ],
        ];
    }

    private static function formatDateRange(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end) {
            return 'Period not recorded';
        }

        return $start->format('d/m/Y').' – '.$end->format('d/m/Y');
    }

    private static function titleCase(string $key): string
    {
        $spaced = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);

        return ucfirst(trim($spaced));
    }

    private static function formatAssumptionValue(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return fmod((float) $value, 1.0) === 0.0 ? (string) (int) $value : number_format((float) $value, 4, '.', '');
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if ($value === null) {
            return '-';
        }

        return (string) $value;
    }

    /**
     * @param  Collection<int, MeterReading>  $readings
     * @return array<int, array{hour: int, avg_kwh: float}>
     */
    private static function buildAvgLoadProfile($readings): array
    {
        $byHour = [];
        foreach ($readings as $r) {
            $hour = $r->hour_start->copy()->utc()->hour;
            $byHour[$hour]['total'] = ($byHour[$hour]['total'] ?? 0) + (float) $r->consumption_kwh;
            $byHour[$hour]['count'] = ($byHour[$hour]['count'] ?? 0) + 1;
        }

        return array_map(function (int $hour) use ($byHour) {
            $entry = $byHour[$hour] ?? null;

            return ['hour' => $hour, 'avg_kwh' => $entry ? $entry['total'] / $entry['count'] : 0.0];
        }, range(0, 23));
    }

    /**
     * @param  Collection<int, ElectricityPrice>  $prices
     * @return array{0: array<int, array{hour:int, avg_eur_per_mwh: ?float, tier: string}>, 1: array<int, array{label:string, avg_eur_per_mwh: float}>, 2: ?float, 3: ?float}
     */
    private static function buildMarket($prices): array
    {
        $byHour = [];
        $byDay = [];
        $minPrice = null;
        $maxPrice = null;

        foreach ($prices as $p) {
            $eur = (float) $p->eur_per_mwh;
            $minPrice = $minPrice === null ? $eur : min($minPrice, $eur);
            $maxPrice = $maxPrice === null ? $eur : max($maxPrice, $eur);

            $hour = $p->hour_start->copy()->utc()->hour;
            $byHour[$hour]['total'] = ($byHour[$hour]['total'] ?? 0) + $eur;
            $byHour[$hour]['count'] = ($byHour[$hour]['count'] ?? 0) + 1;

            $dayKey = $p->hour_start->copy()->utc()->format('Y-m-d');
            $byDay[$dayKey]['total'] = ($byDay[$dayKey]['total'] ?? 0) + $eur;
            $byDay[$dayKey]['count'] = ($byDay[$dayKey]['count'] ?? 0) + 1;
        }

        $heatmap = array_map(function (int $hour) use ($byHour) {
            $entry = $byHour[$hour] ?? null;
            $avg = $entry ? $entry['total'] / $entry['count'] : null;

            return ['hour' => $hour, 'avg_eur_per_mwh' => $avg, 'tier' => $avg === null ? 'unknown' : PriceTier::classify($avg)];
        }, range(0, 23));

        ksort($byDay);
        $trend = [];
        foreach ($byDay as $day => $entry) {
            $trend[] = [
                'label' => Carbon::parse($day, 'UTC')->format('d M'),
                'avg_eur_per_mwh' => $entry['total'] / $entry['count'],
            ];
        }

        return [$heatmap, $trend, $minPrice, $maxPrice];
    }

    /**
     * @param  array<int, array{hour:int, avg_eur_per_mwh: ?float, tier: string}>  $heatmap
     * @return array<int, int>
     */
    private static function peakCostHours(array $heatmap): array
    {
        $withPrice = array_values(array_filter($heatmap, fn ($c) => $c['avg_eur_per_mwh'] !== null));
        usort($withPrice, fn ($a, $b) => $b['avg_eur_per_mwh'] <=> $a['avg_eur_per_mwh']);
        $top = array_slice($withPrice, 0, 4);
        $hours = array_map(fn ($c) => $c['hour'], $top);
        sort($hours);

        return $hours;
    }

    /**
     * @param  array<int, array{hour:int, avg_eur_per_mwh: ?float, tier: string}>  $heatmap
     * @return array{asset_label: string, start_hour: float, end_hour: float, tier: string}
     */
    private static function toBar(Carbon $startAt, Carbon $endAt, string $assetLabel, array $heatmap): array
    {
        $start = $startAt->copy()->utc();
        $end = $endAt->copy()->utc();
        $startHour = $start->hour + $start->minute / 60;
        $endHour = $end->hour + $end->minute / 60;
        if ($endHour <= $startHour) {
            $endHour = 24; // crosses midnight or same-instant guard
        }
        $midHour = (int) floor(($startHour + $endHour) / 2) % 24;
        $midCell = $heatmap[$midHour] ?? null;

        return [
            'asset_label' => $assetLabel,
            'start_hour' => $startHour,
            'end_hour' => $endHour,
            'tier' => $midCell['tier'] ?? 'unknown',
        ];
    }
}
