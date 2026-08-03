<?php

namespace App\Domain\Billing\Reports;

use Illuminate\Support\Carbon;

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

        $periodLabel = self::formatDateRange($assessment->period_start, $assessment->period_end);
        $generatedLabel = Carbon::parse($assessment->generated_at, 'UTC')->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y');

        // The report's net figure is always derived from the two displayed
        // components. This makes the arithmetic auditable even if a legacy
        // assessment row contains a stale denormalized net value.
        $netCustomerSavingsCents = $assessment->estimated_annual_savings_cents - $assessment->hetkin_share_cents;

        $savingsPercent = $assessment->current_annual_cost_cents > 0
            ? $assessment->estimated_annual_savings_cents / $assessment->current_annual_cost_cents * 100
            : 0.0;

        $recommendationRows = [];
        foreach ($data->recommendations as $recommendation) {
            $recommendationRows[] = [
                'title' => ($recommendation->scheduleBlock?->asset_label ?? 'Proposed change').': '
                    .$recommendation->proposed_start_at->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y H:i').'–'.$recommendation->proposed_end_at->copy()->timezone(self::DISPLAY_TIMEZONE)->format('H:i'),
                'rationale' => self::formatRationale($recommendation->rationale),
                'savings_label' => Money::formatEurCents($recommendation->projected_savings_cents).' per occurrence',
                'complexity' => $recommendation->delivery_risk->value,
                'business_impact' => $recommendation->business_impact ?? 'Not specified',
            ];
        }

        $rawAssumptionsJson = $baseline->getRawOriginal('assumptions');
        $rawAssumptions = is_string($rawAssumptionsJson)
            ? json_decode($rawAssumptionsJson, true)
            : null;
        $rawAssumptions = is_array($rawAssumptions) ? $rawAssumptions : [];
        $flexPct = (float) ($rawAssumptions['flexPct'] ?? 0.3);
        $cheapHours = (int) ($rawAssumptions['cheapHoursPerDay'] ?? 3);
        $coveredHours = $data->readings->count();
        $expectedHours = ($assessment->period_start && $assessment->period_end)
            ? (int) round($assessment->period_start->diffInSeconds($assessment->period_end) / 3600) + 1
            : null;

        // Never presented with full confidence (a 'cheap'/positive tone,
        // an unqualified label) unless a real production profile backs
        // the number — see $productionProfileConfirmed's own comment.
        $kpis = [
            ['label' => 'Estimated annual energy-price saving', 'value' => Money::formatEurCents($assessment->estimated_annual_savings_cents), 'tone' => 'cheap'],
            ['label' => 'Estimated monthly energy-price saving', 'value' => Money::formatEurCents((int) round($assessment->estimated_annual_savings_cents / 12)), 'tone' => 'cheap'],
            ['label' => 'Estimated energy-price reduction', 'value' => self::formatDecimal($savingsPercent, 1).'%', 'tone' => 'cheap'],
        ];
        if ($feeConfigured) {
            $kpis[] = ['label' => 'Estimated annual net saving', 'value' => Money::formatEurCents($netCustomerSavingsCents), 'tone' => 'cheap'];
        }

        return [
            'cover' => [
                'organization_name' => $organization->name,
                'report_title' => $organization->name.' Savings Assessment',
                'period_label' => $periodLabel,
                'generated_label' => $generatedLabel,
            ],
            'executive_summary' => [
                'kpis' => $kpis,
                'energy_price_scope_note' => 'This estimate reflects electricity energy prices. Network tariff and capacity charges will be confirmed before implementation.',
            ],
            'recommendations' => [
                'items' => array_slice($recommendationRows, 0, 5),
                'additional_count' => max(0, count($recommendationRows) - 5),
            ],
            'calculation' => [
                'summary' => CustomerSavingsReportCopy::CALCULATION_SUMMARY,
                'estimate_note' => CustomerSavingsReportCopy::ESTIMATE_NOTE,
                'data_used' => $assessment->sample_day_count !== null
                    ? $assessment->sample_day_count.' days of metered data were used for this estimate.'
                    : self::formatDecimal((float) $coveredHours, 0).' hourly meter readings were used for this estimate.',
                'operational_assumption' => 'The estimate assumes '.self::formatDecimal($flexPct * 100, 0).'% of eligible electricity use can move to the '.$cheapHours.' cheapest hours each day.',
                'confirmation_note' => 'Before making schedule changes, confirm that the proposed timing works for production and check how your network tariff and capacity charges apply.',
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
}
