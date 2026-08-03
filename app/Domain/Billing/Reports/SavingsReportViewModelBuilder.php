<?php

namespace App\Domain\Billing\Reports;

use App\Domain\Metering\Enums\MeterDataSource;
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
    private const DISPLAY_TIMEZONE = 'Europe/Helsinki';

    /**
     * @return array<string, mixed>
     */
    public static function build(SavingsReportData $data): array
    {
        $assessment = $data->assessment;
        $organization = $data->organization;
        $baseline = $data->baseline;

        // Single source of truth for "does this customer have a fee set
        // yet" — distinct from "the fee is genuinely zero." Both
        // previously collapsed to hetkin_share_cents === 0, which is why
        // the report could show a fabricated number in one place and
        // "n/a"/€0.00 in another for the exact same underlying state.
        $feeConfigured = $organization->revenue_share_percent !== null;

        // The headline savings figure (ProvisionalHeuristicStrategy) is
        // computed purely from raw meter readings + prices — it applies
        // the same 30%-shiftable assumption to every organization
        // regardless of whether any of their load has actually been
        // confirmed flexible. "Confirmed" here means the closest thing
        // this app has to real evidence of that: at least one real
        // schedule block marked Unmanned with a known energy draw — the
        // exact same eligibility check the optimizer itself uses
        // (ScheduleBlock::isEligibleForOptimization()). Distinct from
        // $hasRecommendations below: a customer can have confirmed,
        // eligible blocks and still have zero recommendations if
        // optimization hasn't been run yet.
        $productionProfileConfirmed = $data->scheduleBlocks->contains(fn ($b) => $b->isEligibleForOptimization());
        $hasRecommendations = $data->recommendations->isNotEmpty();

        $periodLabel = self::formatDateRange($assessment->period_start, $assessment->period_end);
        $generatedLabel = Carbon::parse($assessment->generated_at, 'UTC')->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y');

        $avgLoadProfile = self::buildAvgLoadProfile($data->readings);
        [$heatmap, $trend, $minPrice, $maxPrice] = self::buildMarket($data->prices);
        $peakCostHours = self::peakCostHours($heatmap);

        $waterfall = [
            ['label' => 'Total projected savings', 'delta_cents' => $assessment->estimated_annual_savings_cents, 'cumulative_cents' => $assessment->estimated_annual_savings_cents, 'kind' => 'start'],
            ['label' => "Hetkin's service fee", 'delta_cents' => -$assessment->hetkin_share_cents, 'cumulative_cents' => $assessment->estimated_annual_savings_cents - $assessment->hetkin_share_cents, 'kind' => 'decrease'],
            ['label' => 'Net customer savings', 'delta_cents' => 0, 'cumulative_cents' => $assessment->estimated_annual_savings_cents - $assessment->hetkin_share_cents, 'kind' => 'end'],
        ];

        // The report's net figure is always derived from the two displayed
        // components. This makes the arithmetic auditable even if a legacy
        // assessment row contains a stale denormalized net value.
        $netCustomerSavingsCents = $assessment->estimated_annual_savings_cents - $assessment->hetkin_share_cents;

        $savingsToFeeRatio = $assessment->hetkin_share_cents > 0
            ? $netCustomerSavingsCents / $assessment->hetkin_share_cents
            : null;
        $savingsPercent = $assessment->current_annual_cost_cents > 0
            ? $assessment->estimated_annual_savings_cents / $assessment->current_annual_cost_cents * 100
            : 0.0;

        // One string, computed once, used everywhere a fee-related figure
        // would otherwise appear — the KPI box, the ROI sentence, and the
        // savings table's fee/net rows all read from this same value
        // rather than each independently deciding how to render "no fee
        // yet," which is exactly how the KPI ("n/a"), the savings table
        // (a fabricated €0.00), and the waterfall (duplicated net value,
        // a separate bug) ended up showing three different things for
        // the same underlying state.
        $netAfterFeeValue = match (true) {
            ! $feeConfigured => 'Fee not yet configured',
            $savingsToFeeRatio !== null => self::formatDecimal($savingsToFeeRatio, 1).'x ROI',
            default => 'No fee — 100% retained',
        };
        $roiLabel = match (true) {
            ! $feeConfigured => 'Fee not yet configured',
            $savingsToFeeRatio !== null => self::formatDecimal($savingsToFeeRatio, 1)."x — every €1 of Hetkin's fee returns ".self::formatDecimal($savingsToFeeRatio, 1).'x in net customer savings',
            default => "No fee applies to this assessment — 100% of savings go to {$organization->name}.",
        };

        $current = $data->scheduleBlocks->map(fn (ScheduleBlock $b) => self::toBar($b->start_at, $b->end_at, $b->asset_label, $heatmap))->values()->all();
        $recommendedBars = $data->recommendations->map(fn (Recommendation $r) => self::toBar(
            $r->proposed_start_at, $r->proposed_end_at, $r->scheduleBlock?->asset_label ?? 'Proposed block', $heatmap,
        ))->values()->all();

        $calloutLines = $data->recommendations->take(4)->map(function (Recommendation $r) {
            $asset = $r->scheduleBlock?->asset_label ?? 'This block';

            return "{$asset} — ".self::formatRationale($r->rationale).' ('.Money::formatEurCents($r->projected_savings_cents).' per occurrence)';
        })->values()->all();

        $recommendationRows = $data->recommendations->map(fn (Recommendation $r) => [
            'title' => ($r->scheduleBlock?->asset_label ?? 'Proposed change').': '
                .$r->proposed_start_at->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y H:i').'–'.$r->proposed_end_at->copy()->timezone(self::DISPLAY_TIMEZONE)->format('H:i'),
            'rationale' => self::formatRationale($r->rationale),
            'savings_label' => Money::formatEurCents($r->projected_savings_cents).' per occurrence',
            'complexity' => $r->delivery_risk->value,
            'business_impact' => $r->business_impact ?? 'Not specified',
        ])->values()->all();

        $rawAssumptionsJson = $baseline->getRawOriginal('assumptions');
        $rawAssumptions = is_string($rawAssumptionsJson)
            ? json_decode($rawAssumptionsJson, true)
            : null;
        $rawAssumptions = is_array($rawAssumptions) ? $rawAssumptions : [];
        $assumptions = self::buildAssumptions($rawAssumptions);
        $flexPct = (float) ($rawAssumptions['flexPct'] ?? 0.3);
        $cheapHours = (int) ($rawAssumptions['cheapHoursPerDay'] ?? 3);
        $exclusions = [
            'Grid connection fees and taxes, unless explicitly listed in Information used above',
            'Equipment ramp-up/ramp-down energy draw not separately metered',
            "Demand (capacity) charges, if applicable to this customer's electricity contract",
            'Non-electricity operating costs',
        ];
        $coveredHours = $data->readings->count();
        $expectedHours = ($assessment->period_start && $assessment->period_end)
            ? (int) round($assessment->period_start->diffInSeconds($assessment->period_end) / 3600) + 1
            : null;

        // Never presented with full confidence (a 'cheap'/positive tone,
        // an unqualified label) unless a real production profile backs
        // the number — see $productionProfileConfirmed's own comment.
        $kpis = [
            ['label' => $productionProfileConfirmed ? 'Annual savings' : 'Annual savings (illustrative)', 'value' => Money::formatEurCents($assessment->estimated_annual_savings_cents), 'tone' => $productionProfileConfirmed ? 'cheap' : 'neutral'],
            ['label' => 'Monthly savings', 'value' => Money::formatEurCents((int) round($assessment->estimated_annual_savings_cents / 12)), 'tone' => $productionProfileConfirmed ? 'cheap' : 'neutral'],
            ['label' => $productionProfileConfirmed ? 'Avg. cost reduction' : 'Avg. cost reduction (illustrative)', 'value' => self::formatDecimal($savingsPercent, 1).'%', 'tone' => $productionProfileConfirmed ? 'cheap' : 'neutral'],
            ['label' => 'Net after Hetkin fee', 'value' => $netAfterFeeValue, 'tone' => 'neutral'],
        ];

        $processTypeLabel = $organization->process_type?->label() ?? 'Not set';

        return [
            'cover' => [
                'organization_name' => $organization->name,
                // Issue 3's fix: a report with no real recommendations
                // yet reads as unfinished under the "Assessment" framing
                // ("here's what to change" left blank) — "Diagnostic"
                // reframes the same empty state as an intentional first
                // step in a sequence.
                'report_title' => $hasRecommendations ? 'Savings Assessment Report' : 'Savings Diagnostic',
                'period_label' => $periodLabel,
                'generated_label' => $generatedLabel,
                'version' => $data->versionNumber,
                'assessment_status' => $assessment->status->value,
            ],
            'executive_summary' => [
                'situation' => "{$organization->name}'s production schedule currently runs against the electricity spot market rather than with it.",
                'kpis' => $kpis,
                'waterfall_thumbnail' => $waterfall,
                'fee_configured' => $feeConfigured,
                'production_profile_confirmed' => $productionProfileConfirmed,
                'confidence_badge' => null,
                'confidence_note' => CustomerSavingsReportCopy::ESTIMATE_NOTE,
                'assumption_summary' => 'Estimate assumes '.self::formatDecimal($flexPct * 100, 0).'% of load can move to the '.$cheapHours.' cheapest hours each day'.($productionProfileConfirmed ? '.' : '; this is not yet confirmed against your production schedule.'),
                'key_recommendations' => collect($recommendationRows)->take(3)->pluck('title')->values()->all(),
                'roi_label' => $roiLabel,
                'status_note' => null,
                'next_steps' => $hasRecommendations
                    ? [
                        'Review the recommended schedule changes with production leadership (see Production Schedule Optimization).',
                        'Approve and lock the baseline to begin monthly verification.',
                        'Contact Hetkin to discuss implementation support for the highest-savings recommendations.',
                    ]
                    : [
                        'Add a production schedule (or confirm the existing one) so recommendations can be generated against real, confirmed load data.',
                        'Run the optimization engine once at least one Unmanned block has an estimated energy use.',
                        'Contact Hetkin if you\'d like help scoping which lines/assets are realistically shiftable.',
                    ],
            ],
            'customer_overview' => [
                'company_name' => $organization->name,
                'process_type_label' => $processTypeLabel,
                'period_label' => $periodLabel,
                'metering_points' => $data->meteringPoints->map(function ($mp) {
                    $dataSource = $mp->data_source;
                    $isManualUpload = $dataSource === MeterDataSource::ManualUpload->value;

                    return [
                        'label' => $mp->label,
                        'site_name' => $mp->site->name,
                        'data_source' => $isManualUpload ? 'Manual upload' : 'Datahub connection',
                        'status_label' => $isManualUpload
                            ? 'Data received'
                            : ($mp->connection_healthy ? 'Healthy' : 'Needs attention'),
                    ];
                })->values()->all(),
                'data_coverage' => [
                    'day_count' => $assessment->sample_day_count,
                    'hour_count' => $coveredHours,
                    'expected_hour_count' => $expectedHours,
                ],
                // Issue 18's fix: this free-text field (what the
                // customer told Hetkin about their process during the
                // pilot application) and "do we have confirmed
                // equipment-level schedule data" are two genuinely
                // different things — a customer can have detailed
                // schedule blocks on file while never having filled in
                // this description, or vice versa. Previously this was
                // hardcoded to "Not provided." unconditionally (a
                // leftover from before the pilot-funnel domain existed),
                // which contradicted a report that also showed full
                // equipment-level recommendations. Now genuinely wired to
                // PilotApplication.win_condition, with the separate
                // 'production_data_status' below making clear that a
                // blank description here does NOT mean no schedule data
                // exists.
                'production_profile' => $organization->pilotApplication?->win_condition ?: 'Not provided.',
                'production_data_status' => $productionProfileConfirmed
                    ? 'Confirmed — at least one Unmanned schedule block with a known energy use is on file.'
                    : 'Not yet confirmed — no Unmanned schedule block with a known energy use is on file yet.',
            ],
            'baseline' => [
                'current_annual_cost_label' => Money::formatEurCents($assessment->current_annual_cost_cents),
                'avg_load_profile' => $avgLoadProfile,
                'peak_cost_hours' => $peakCostHours,
                'cost_driver_note' => count($peakCostHours) > 0
                    ? 'Consumption during local Finland hours '.implode(', ', $peakCostHours).":00 overlaps with this period's most expensive electricity — the primary driver of avoidable cost."
                    : 'Not enough matched price data to identify specific cost-driving hours.',
            ],
            'market' => [
                'heatmap' => $heatmap,
                'min_price_label' => $minPrice !== null ? self::formatEurPerMwh($minPrice) : 'n/a',
                'max_price_label' => $maxPrice !== null ? self::formatEurPerMwh($maxPrice) : 'n/a',
                'volatility_note' => ($minPrice !== null && $maxPrice !== null)
                    ? 'Prices ranged from '.self::formatEurPerMwh($minPrice).' to '.self::formatEurPerMwh($maxPrice).' over this period — a '.self::formatDecimal($maxPrice / max(abs($minPrice), 1), 1).'x spread between the cheapest and most expensive hour.'
                    : 'Insufficient price data for this period.',
                'trend' => $trend,
                'trend_mode' => count($trend) > 14 ? 'chart' : 'table',
                'negative_price_note' => $minPrice !== null && $minPrice < 0
                    ? 'Negative prices are a real Nordic market condition: when electricity supply exceeds demand, producers may effectively pay consumers to use electricity.'
                    : null,
                'sensitivity_note' => self::buildSensitivityNote($trend),
            ],
            'savings' => [
                'waterfall' => $waterfall,
                'fee_configured' => $feeConfigured,
                'table' => [
                    ['Current annual cost', Money::formatEurCents($assessment->current_annual_cost_cents)],
                    ['Optimized annual cost', Money::formatEurCents($assessment->optimized_annual_cost_cents)],
                    ['Total projected savings', Money::formatEurCents($assessment->estimated_annual_savings_cents)],
                    ['Savings percentage', self::formatDecimal($savingsPercent, 1).'%'],
                    ["Hetkin's service fee", $feeConfigured ? Money::formatEurCents($assessment->hetkin_share_cents) : 'Fee not yet configured'],
                    ['Net customer savings', $feeConfigured ? Money::formatEurCents($netCustomerSavingsCents) : 'Fee not yet configured'],
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
            'calculation' => [
                'summary' => CustomerSavingsReportCopy::CALCULATION_SUMMARY,
                'estimate_note' => CustomerSavingsReportCopy::ESTIMATE_NOTE,
                'coverage_note' => $expectedHours !== null
                    ? self::formatDecimal((float) $coveredHours, 0).' of '.self::formatDecimal((float) $expectedHours, 0).' expected hourly readings ('.self::formatDecimal(round($coveredHours / $expectedHours * 100), 0).'% coverage)'
                    : self::formatDecimal((float) $coveredHours, 0).' hourly readings',
                'price_source' => 'ENTSO-E Transparency Platform, day-ahead auction, Finland (FI) bidding zone',
                'assumptions' => $assumptions,
                'exclusions' => $exclusions,
            ],
        ];
    }

    private static function formatDateRange(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end) {
            return 'Period not recorded';
        }

        return $start->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y').' – '.$end->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y');
    }

    /**
     * @param  Collection<int, MeterReading>  $readings
     * @return array<int, array{hour: int, avg_kwh: float}>
     */
    private static function buildAvgLoadProfile($readings): array
    {
        $byHour = [];
        foreach ($readings as $r) {
            $hour = $r->hour_start->copy()->timezone(self::DISPLAY_TIMEZONE)->hour;
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

            $hour = $p->hour_start->copy()->timezone(self::DISPLAY_TIMEZONE)->hour;
            $byHour[$hour]['total'] = ($byHour[$hour]['total'] ?? 0) + $eur;
            $byHour[$hour]['count'] = ($byHour[$hour]['count'] ?? 0) + 1;

            $dayKey = $p->hour_start->copy()->timezone(self::DISPLAY_TIMEZONE)->format('Y-m-d');
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
                'label' => Carbon::parse($day, self::DISPLAY_TIMEZONE)->format('d M'),
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
        $start = $startAt->copy()->timezone(self::DISPLAY_TIMEZONE);
        $end = $endAt->copy()->timezone(self::DISPLAY_TIMEZONE);
        $startHour = $start->hour + $start->minute / 60;
        $endHour = $end->hour + $end->minute / 60;
        if ($endHour <= $startHour) {
            $endHour = 24; // crosses midnight or same-instant guard
        }
        $midHour = (int) floor(($startHour + $endHour) / 2) % 24;
        $midCell = $heatmap[$midHour] ?? null;

        return [
            'asset_label' => $assetLabel,
            'date_label' => $start->format('d/m/Y'),
            'time_label' => $start->format('H:i').'–'.$end->format('H:i').' Finland time',
            'start_hour' => $startHour,
            'end_hour' => $endHour,
            'tier' => $midCell['tier'] ?? 'unknown',
        ];
    }

    /** @param array<string, mixed> $raw */
    private static function buildAssumptions(array $raw): array
    {
        $assumptions = [];
        if (isset($raw['flexPct'])) {
            $assumptions[] = ['Share of load assumed shiftable', self::formatDecimal((float) $raw['flexPct'] * 100, 1).'%'];
        }
        if (isset($raw['cheapHoursPerDay'])) {
            $assumptions[] = ['Cheapest hours targeted per day', (string) $raw['cheapHoursPerDay'].' hours'];
        }
        if (isset($raw['annualizeFactor'])) {
            $days = $raw['sampleDayCount'] ?? null;
            $sample = is_numeric($days) ? (int) $days.'-day sample' : 'sample period';
            $assumptions[] = ['Annualization basis', "{$sample} × ".self::formatDecimal((float) $raw['annualizeFactor'], 2).' to estimate one year'];
        }

        return $assumptions;
    }

    private static function formatDecimal(float $value, int $decimals): string
    {
        return number_format($value, $decimals, ',', ' ');
    }

    private static function formatEurPerMwh(float $value): string
    {
        return self::formatDecimal($value, 1).' €/MWh';
    }

    private static function formatRationale(string $rationale): string
    {
        return preg_replace_callback('/~€(-?\d+(?:\.\d+)?)\/MWh/', fn (array $matches) => '~'.self::formatEurPerMwh((float) $matches[1]), $rationale) ?? $rationale;
    }

    /** @param array<int, array{label: string, avg_eur_per_mwh: float}> $trend */
    private static function buildSensitivityNote(array $trend): ?string
    {
        if (count($trend) < 2) {
            return null;
        }

        $average = array_sum(array_column($trend, 'avg_eur_per_mwh')) / count($trend);
        $outliers = array_filter($trend, fn (array $point) => abs($point['avg_eur_per_mwh']) > max(1, abs($average) * 2));

        return count($outliers) > 0
            ? count($outliers).' of '.count($trend).' days had unusually volatile average prices; annualized savings may vary week to week.'
            : null;
    }
}
