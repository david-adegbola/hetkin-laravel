<?php

namespace App\Domain\Billing\Reports;

/**
 * Final customer-facing contract for savings reports.
 *
 * Assessments store immutable report snapshots. This adapter deliberately
 * reduces both new and historic snapshots to the same decision-focused
 * representation, while leaving the original audit data untouched.
 */
final class CustomerSavingsReportCopy
{
    public const CALCULATION_SUMMARY = 'Based on your uploaded schedule, estimated energy use, and hourly electricity prices.';

    public const ESTIMATE_NOTE = 'Estimates are based on the information provided and should be validated before operational changes are made.';

    /**
     * @param  array<string, mixed>  $viewModel
     * @return array<string, mixed>
     */
    public static function present(array $viewModel): array
    {
        /** @var array<string, mixed> $cover */
        $cover = is_array($viewModel['cover'] ?? null) ? $viewModel['cover'] : [];
        $organizationName = (string) ($cover['organization_name'] ?? $viewModel['customer_overview']['company_name'] ?? 'Your company');

        /** @var array<string, mixed> $executiveSummary */
        $executiveSummary = is_array($viewModel['executive_summary'] ?? null) ? $viewModel['executive_summary'] : [];
        /** @var array<int, array<string, mixed>> $legacyKpis */
        $legacyKpis = is_array($executiveSummary['kpis'] ?? null) ? $executiveSummary['kpis'] : [];
        $feeConfigured = (bool) ($executiveSummary['fee_configured'] ?? false);

        /** @var array<int, array<string, mixed>> $recommendationRows */
        $recommendationRows = self::recommendationRows($viewModel['recommendations'] ?? []);
        /** @var array<string, mixed> $calculation */
        $calculation = is_array($viewModel['calculation'] ?? null) ? $viewModel['calculation'] : [];
        /** @var array<string, mixed> $overview */
        $overview = is_array($viewModel['customer_overview'] ?? null) ? $viewModel['customer_overview'] : [];

        return [
            'cover' => [
                'organization_name' => $organizationName,
                'report_title' => $organizationName.' Savings Assessment',
                'period_label' => (string) ($cover['period_label'] ?? $overview['period_label'] ?? 'Period not recorded'),
                'generated_label' => self::generatedLabel((string) ($cover['generated_label'] ?? '')),
            ],
            'executive_summary' => [
                'kpis' => self::customerKpis($legacyKpis, $feeConfigured),
                'energy_price_scope_note' => 'This estimate reflects electricity energy prices. Network tariff and capacity charges will be confirmed before implementation.',
            ],
            'recommendations' => [
                'items' => array_slice($recommendationRows, 0, 5),
                'additional_count' => max(0, count($recommendationRows) - 5),
            ],
            'calculation' => [
                'summary' => self::CALCULATION_SUMMARY,
                'estimate_note' => self::ESTIMATE_NOTE,
                'data_used' => self::dataUsed($calculation, $overview),
                'operational_assumption' => self::operationalAssumption($calculation, $executiveSummary),
                'confirmation_note' => 'Before making schedule changes, confirm that the proposed timing works for production and check how your network tariff and capacity charges apply.',
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $kpis
     * @return array<int, array{label: string, value: string, tone: string}>
     */
    private static function customerKpis(array $kpis, bool $feeConfigured): array
    {
        $annual = self::kpiValue($kpis, 'annual energy-price') ?: self::kpiValue($kpis, 'annual savings');
        $monthly = self::kpiValue($kpis, 'monthly energy-price') ?: self::kpiValue($kpis, 'monthly savings');
        $reduction = self::kpiValue($kpis, 'reduction');
        $net = self::kpiValue($kpis, 'net');

        $customerKpis = [
            ['label' => 'Estimated annual energy-price saving', 'value' => $annual, 'tone' => 'cheap'],
            ['label' => 'Estimated monthly energy-price saving', 'value' => $monthly, 'tone' => 'cheap'],
            ['label' => 'Estimated energy-price reduction', 'value' => $reduction, 'tone' => 'cheap'],
        ];
        if ($feeConfigured && $net !== '') {
            $customerKpis[] = ['label' => 'Estimated annual net saving', 'value' => $net, 'tone' => 'cheap'];
        }

        return array_values(array_filter($customerKpis, fn (array $kpi) => $kpi['value'] !== ''));
    }

    /** @param array<int, array<string, mixed>> $kpis */
    private static function kpiValue(array $kpis, string $needle): string
    {
        foreach ($kpis as $kpi) {
            if (str_contains(strtolower((string) ($kpi['label'] ?? '')), $needle)) {
                return (string) ($kpi['value'] ?? '');
            }
        }

        return '';
    }

    /** @return array<int, array<string, mixed>> */
    private static function recommendationRows(mixed $recommendations): array
    {
        if (is_array($recommendations) && isset($recommendations['items']) && is_array($recommendations['items'])) {
            return array_values($recommendations['items']);
        }

        return is_array($recommendations) ? array_values($recommendations) : [];
    }

    /** @param array<string, mixed> $calculation
     * @param  array<string, mixed>  $overview
     */
    private static function dataUsed(array $calculation, array $overview): string
    {
        if (is_string($calculation['data_used'] ?? null) && $calculation['data_used'] !== '') {
            return $calculation['data_used'];
        }

        $days = $overview['data_coverage']['day_count'] ?? null;
        if (is_numeric($days)) {
            return (int) $days.' days of metered data were used for this estimate.';
        }

        return 'Metered data was used for this estimate.';
    }

    /** @param array<string, mixed> $calculation
     * @param  array<string, mixed>  $executiveSummary
     */
    private static function operationalAssumption(array $calculation, array $executiveSummary): string
    {
        if (is_string($calculation['operational_assumption'] ?? null) && $calculation['operational_assumption'] !== '') {
            return $calculation['operational_assumption'];
        }

        $summary = (string) ($executiveSummary['assumption_summary'] ?? '');

        return $summary !== ''
            ? preg_replace('/;.*$/', '.', $summary) ?? $summary
            : 'The proposed schedule changes should be reviewed with your production team.';
    }

    private static function generatedLabel(string $generatedLabel): string
    {
        return preg_replace('/\s*·\s*Methodology\s+.*$/i', '', $generatedLabel) ?? $generatedLabel;
    }
}
