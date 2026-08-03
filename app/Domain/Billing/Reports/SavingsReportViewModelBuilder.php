<?php

namespace App\Domain\Billing\Reports;

use App\Domain\Optimization\Enums\DeliveryRisk;
use App\Domain\Optimization\Enums\RecommendationDecisionType;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Builds the single, customer-facing decision report used by web and PDF. */
final class SavingsReportViewModelBuilder
{
    private const DISPLAY_TIMEZONE = 'Europe/Helsinki';

    /** @return array<string, mixed> */
    public static function build(SavingsReportData $data): array
    {
        $assessment = $data->assessment;
        $organization = $data->organization;
        $assumptions = self::assumptions($data->baseline->getRawOriginal('assumptions'));
        $annualSavings = (int) $assessment->estimated_annual_savings_cents;
        $feeConfigured = $organization->revenue_share_percent !== null;
        $recommendations = self::recommendations($data, $assumptions['annualizeFactor'] ?? null);
        $sampleDays = $assessment->sample_day_count ?? self::meteredDays($data);
        $hasSchedule = $assessment->optimization_run_id !== null;
        $confidence = self::confidence($sampleDays, $hasSchedule, $recommendations);

        $kpis = [
            ['label' => 'Estimated annual energy-price saving', 'value' => Money::formatEurCents($annualSavings)],
            ['label' => 'Estimated monthly energy-price saving', 'value' => Money::formatEurCents((int) round($annualSavings / 12))],
            ['label' => 'Estimated energy-price reduction', 'value' => self::percent($annualSavings, (int) $assessment->current_annual_cost_cents)],
        ];
        if ($feeConfigured) {
            $kpis[] = ['label' => 'Estimated annual net saving', 'value' => Money::formatEurCents($assessment->netCustomerSavingsCents())];
        }

        return [
            'report_version' => 2,
            'cover' => [
                'organization_name' => $organization->name,
                'report_title' => $organization->name.' Savings Assessment',
                'period_label' => self::dateRange($assessment->period_start, $assessment->period_end),
                'generated_label' => Carbon::parse($assessment->generated_at, 'UTC')->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y'),
            ],
            'opportunity' => [
                'status' => self::opportunityStatus($sampleDays, $recommendations),
                'headline' => 'Estimated annual energy-price saving',
                'kpis' => $kpis,
                'meaning' => 'This is an estimate of the electricity energy cost that may be avoided by moving eligible activity to lower-priced hours.',
                'scope_note' => 'This is an energy-price estimate. Network tariffs, capacity charges and taxes need to be confirmed before implementation.',
                'priority_action' => $recommendations[0] ?? null,
            ],
            'recommendations' => [
                'items' => array_slice($recommendations, 0, 5),
                'additional_count' => max(0, count($recommendations) - 5),
                'source_verified' => $hasSchedule,
                'empty_message' => $hasSchedule
                    ? 'No actionable timing changes were identified in the saved schedule run.'
                    : 'This historical assessment has no saved schedule run, so it does not show recommendation details that cannot be verified.',
            ],
            'evidence' => [
                'profile' => self::hourlyProfile($data),
                'cost_comparison' => [
                    ['label' => 'Current annual energy cost', 'value' => Money::formatEurCents((int) $assessment->current_annual_cost_cents)],
                    ['label' => 'Estimated annual energy cost after changes', 'value' => Money::formatEurCents((int) $assessment->optimized_annual_cost_cents)],
                ],
                'callout' => 'The chart shows when electricity use was recorded. Recommendation timings are shown only from the schedule run saved with this assessment.',
            ],
            'confidence' => [
                'label' => $confidence['label'],
                'reason' => $confidence['reason'],
                'information' => [
                    ['label' => 'Meter data analysed', 'value' => $sampleDays.' days'],
                    ['label' => 'Data period', 'value' => self::dateRange($assessment->period_start, $assessment->period_end)],
                    ['label' => 'Schedule', 'value' => $hasSchedule ? 'Provided and saved with this report' : 'Not saved with this historical report'],
                    ['label' => 'Electricity price input', 'value' => 'Finnish day-ahead hourly electricity prices'],
                    ['label' => 'Time zone', 'value' => 'Finnish local time'],
                ],
                'included' => 'Electricity energy use affected by the proposed timing changes, using meter data, the saved schedule and hourly electricity prices.',
                'confirm_before_action' => 'Confirm network tariffs, capacity charges, taxes, production constraints and any equipment transition energy before implementation.',
                'ending_note' => 'Use this assessment to prioritise a production review; validate the selected changes before putting them into operation.',
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private static function recommendations(SavingsReportData $data, mixed $annualizeFactor): array
    {
        $factor = is_numeric($annualizeFactor) && (float) $annualizeFactor > 0 ? (float) $annualizeFactor : null;

        return $data->recommendations->map(function ($recommendation, int $index) use ($factor): array {
            $block = $recommendation->scheduleBlock;
            $currentStart = self::carbon($block?->start_at);
            $currentEnd = self::carbon($block?->end_at);
            $proposedStart = self::carbon($recommendation->proposed_start_at);
            $proposedEnd = self::carbon($recommendation->proposed_end_at);
            $annualContribution = $factor === null ? null : (int) round($recommendation->projected_savings_cents * $factor);
            $decision = $recommendation->decision?->decision;

            return [
                'priority' => $index === 0 ? 'Start here' : ($index < 3 ? 'Next' : 'Consider later'),
                'asset' => $block->asset_label,
                'what_to_change' => 'Move this activity to the proposed lower-priced time window.',
                'current_timing' => self::timing($currentStart, $currentEnd),
                'proposed_timing' => self::timing($proposedStart, $proposedEnd),
                'why' => self::plainRationale((string) $recommendation->rationale),
                'saving_per_occurrence' => Money::formatEurCents((int) $recommendation->projected_savings_cents).' per occurrence',
                'annual_contribution' => $annualContribution === null ? null : Money::formatEurCents($annualContribution).' estimated annual contribution',
                'operational_impact' => self::operationalImpact($recommendation->delivery_risk),
                'confirmation' => self::isAccepted($decision)
                    ? 'Accepted in the saved schedule review. Confirm the final production plan before implementation.'
                    : 'Confirm with production leadership that this activity can move without changing staffed production.',
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private static function hourlyProfile(SavingsReportData $data): array
    {
        $hours = array_fill(0, 24, 0.0);
        $counts = array_fill(0, 24, 0);
        foreach ($data->readings as $reading) {
            $hourStart = self::carbon($reading->hour_start);
            if ($hourStart === null) {
                continue;
            }
            $hour = (int) $hourStart->timezone(self::DISPLAY_TIMEZONE)->format('G');
            $hours[$hour] += (float) $reading->consumption_kwh;
            $counts[$hour]++;
        }

        $points = [];
        foreach ($hours as $hour => $kwh) {
            $points[] = ['hour' => sprintf('%02d:00', $hour), 'kwh' => $counts[$hour] > 0 ? round($kwh / $counts[$hour], 1) : 0.0];
        }

        return ['title' => 'Typical hourly electricity use', 'unit' => 'Average kWh per recorded hour', 'points' => $points];
    }

    /** @return array{label: string, reason: string} */
    private static function confidence(int $days, bool $hasSchedule, array $recommendations): array
    {
        if ($days < 14) {
            return ['label' => 'Indicative', 'reason' => 'This estimate uses limited meter history and should be treated as an early estimate.'];
        }
        if (! $hasSchedule || $recommendations === []) {
            return ['label' => 'Indicative', 'reason' => 'The meter history is available, but a saved schedule recommendation is not available for this report.'];
        }
        if ($days >= 90 && collect($recommendations)->every(fn (array $item) => str_starts_with($item['confirmation'], 'Accepted'))) {
            return ['label' => 'High', 'reason' => 'This estimate uses substantial meter history and the saved schedule changes have been reviewed.'];
        }

        return ['label' => 'Moderate', 'reason' => 'This estimate uses representative meter history and a saved schedule; production constraints still need confirmation.'];
    }

    private static function opportunityStatus(int $days, array $recommendations): string
    {
        if ($days < 14 || $recommendations === []) {
            return 'More data needed';
        }

        return $days >= 30 ? 'Ready to review' : 'Needs production confirmation';
    }

    private static function meteredDays(SavingsReportData $data): int
    {
        $days = $data->readings
            ->map(fn ($reading) => self::carbon($reading->hour_start)?->timezone(self::DISPLAY_TIMEZONE)->toDateString())
            ->filter()
            ->unique()
            ->count();

        return max(1, $days);
    }

    /** @return array<string, mixed> */
    private static function assumptions(mixed $raw): array
    {
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($decoded) ? $decoded : [];
    }

    private static function dateRange(string|DateTimeInterface|null $start, string|DateTimeInterface|null $end): string
    {
        $start = self::carbon($start);
        $end = self::carbon($end);
        if ($start === null || $end === null) {
            return 'Period not recorded';
        }

        return $start->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y').' – '.$end->copy()->timezone(self::DISPLAY_TIMEZONE)->format('d/m/Y');
    }

    private static function timing(?Carbon $start, ?Carbon $end): string
    {
        if (! $start || ! $end) {
            return 'Timing not available';
        }
        $start = $start->copy()->timezone(self::DISPLAY_TIMEZONE);
        $end = $end->copy()->timezone(self::DISPLAY_TIMEZONE);

        return $start->format('d/m/Y H:i').' → '.$end->format($start->isSameDay($end) ? 'H:i' : 'd/m/Y H:i');
    }

    private static function percent(int $saving, int $current): string
    {
        return $current > 0 ? number_format($saving / $current * 100, 1, ',', ' ').'%' : '—';
    }

    private static function plainRationale(string $rationale): string
    {
        $rationale = preg_replace('/~?€(-?\d+(?:\.\d+)?)\/MWh/', 'hourly electricity-price difference', $rationale) ?? $rationale;

        return str_ireplace(['spot market', 'unmanned'], ['hourly electricity price', 'reschedulable'], $rationale);
    }

    private static function operationalImpact(DeliveryRisk|string|null $risk): string
    {
        $risk = $risk instanceof DeliveryRisk ? $risk->value : $risk;

        return match ($risk) {
            'LOW' => 'Low operational impact — confirm the activity is not needed during the proposed window.',
            'MEDIUM' => 'Moderate operational impact — confirm handovers, quality checks and production timing.',
            'HIGH' => 'Higher operational impact — production leadership should validate constraints before any change.',
            default => 'Confirm the operational impact with production leadership before implementation.',
        };
    }

    private static function carbon(string|DateTimeInterface|null $value): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value);
    }

    private static function isAccepted(RecommendationDecisionType|string|null $decision): bool
    {
        return $decision instanceof RecommendationDecisionType
            ? $decision === RecommendationDecisionType::Accepted
            : $decision === RecommendationDecisionType::Accepted->value;
    }
}
