<?php

namespace App\Domain\Billing\Reports;

/**
 * Customer-copy boundary for stored report snapshots.
 *
 * Version 2 is the intentionally small decision-report contract. Older
 * snapshots are reduced to the same contract rather than leaking their old
 * technical sections when customers reopen them.
 */
final class CustomerSavingsReportCopy
{
    /** @param array<string, mixed> $viewModel
     * @return array<string, mixed>
     */
    public static function present(array $viewModel): array
    {
        if (($viewModel['report_version'] ?? null) === 2) {
            return $viewModel;
        }

        $cover = is_array($viewModel['cover'] ?? null) ? $viewModel['cover'] : [];
        $overview = is_array($viewModel['customer_overview'] ?? null) ? $viewModel['customer_overview'] : [];
        $summary = is_array($viewModel['executive_summary'] ?? null) ? $viewModel['executive_summary'] : [];
        $organization = (string) ($cover['organization_name'] ?? $overview['company_name'] ?? 'Your company');
        $kpis = self::kpis(is_array($summary['kpis'] ?? null) ? $summary['kpis'] : []);
        $days = $overview['data_coverage']['day_count'] ?? null;
        $daysText = is_numeric($days) ? (int) $days.' days' : 'an unrecorded number of days';

        return [
            'report_version' => 2,
            'cover' => [
                'organization_name' => $organization,
                'report_title' => $organization.' Savings Assessment',
                'period_label' => (string) ($cover['period_label'] ?? 'Period not recorded'),
                'generated_label' => self::generatedLabel((string) ($cover['generated_label'] ?? '')),
            ],
            'opportunity' => [
                'status' => 'Needs production confirmation',
                'headline' => 'Estimated annual energy-price saving',
                'kpis' => $kpis,
                'meaning' => 'This is an estimate of the electricity energy cost that may be avoided by moving eligible activity to lower-priced hours.',
                'scope_note' => 'This is an energy-price estimate. Network tariffs, capacity charges and taxes need to be confirmed before implementation.',
                'priority_action' => null,
            ],
            'recommendations' => [
                'items' => [],
                'additional_count' => 0,
                'source_verified' => false,
                'empty_message' => 'This historical assessment has no saved schedule run, so it does not show recommendation details that cannot be verified.',
            ],
            'evidence' => [
                'profile' => ['title' => 'Typical hourly electricity use', 'unit' => 'Average kWh per recorded hour', 'points' => []],
                'cost_comparison' => [],
                'callout' => 'This historical report retains its saving estimate, but not enough saved evidence to reproduce a customer chart.',
            ],
            'confidence' => [
                'label' => 'Indicative',
                'reason' => 'This is a historical estimate with '.$daysText.' of recorded meter data; validate it with current production information.',
                'information' => [
                    ['label' => 'Meter data analysed', 'value' => $daysText],
                    ['label' => 'Data period', 'value' => (string) ($cover['period_label'] ?? 'Period not recorded')],
                    ['label' => 'Schedule', 'value' => 'Not saved with this historical report'],
                    ['label' => 'Electricity price input', 'value' => 'Hourly electricity prices'],
                    ['label' => 'Time zone', 'value' => 'Finnish local time'],
                ],
                'included' => 'Electricity energy use represented in the original estimate.',
                'confirm_before_action' => 'Confirm network tariffs, capacity charges, taxes, production constraints and any equipment transition energy before implementation.',
                'ending_note' => 'Use this assessment to prioritise a production review; validate the selected changes before putting them into operation.',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $source
     * @return array<int, array{label: string, value: string}>
     */
    private static function kpis(array $source): array
    {
        $wanted = [
            'annual energy-price' => 'Estimated annual energy-price saving',
            'annual savings' => 'Estimated annual energy-price saving',
            'monthly energy-price' => 'Estimated monthly energy-price saving',
            'monthly savings' => 'Estimated monthly energy-price saving',
            'reduction' => 'Estimated energy-price reduction',
            'net' => 'Estimated annual net saving',
        ];
        $result = [];
        foreach ($source as $kpi) {
            $label = strtolower((string) ($kpi['label'] ?? ''));
            foreach ($wanted as $needle => $customerLabel) {
                if (str_contains($label, $needle) && ($value = (string) ($kpi['value'] ?? '')) !== '') {
                    $result[] = ['label' => $customerLabel, 'value' => $value];
                    break;
                }
            }
        }

        return $result;
    }

    private static function generatedLabel(string $label): string
    {
        return preg_replace('/\s*·\s*[^·]*$/', '', $label) ?? $label;
    }
}
