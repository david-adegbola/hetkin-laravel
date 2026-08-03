<?php

namespace App\Domain\Billing\Reports;

/**
 * Final customer-facing copy boundary for savings-report view models.
 *
 * Reports are snapshotted when generated, so changing the builder alone
 * would leave historical portal/PDF reports exposing retired internal
 * methodology identifiers. This adapter makes the presentation contract
 * safe for both new and legacy snapshots without changing their immutable
 * underlying audit data.
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
        $cover['generated_label'] = preg_replace('/\\s*·\\s*Methodology\\s+.*$/i', '', (string) ($cover['generated_label'] ?? ''));
        unset($cover['version'], $cover['is_provisional'], $cover['assessment_status'], $cover['baseline_status']);
        $viewModel['cover'] = $cover;

        /** @var array<string, mixed> $executiveSummary */
        $executiveSummary = is_array($viewModel['executive_summary'] ?? null) ? $viewModel['executive_summary'] : [];
        $executiveSummary['confidence_badge'] = null;
        $executiveSummary['confidence_note'] = self::ESTIMATE_NOTE;
        $executiveSummary['status_note'] = null;
        $viewModel['executive_summary'] = $executiveSummary;

        /** @var array<string, mixed> $calculation */
        $calculation = is_array($viewModel['calculation'] ?? null) ? $viewModel['calculation'] : [];
        /** @var array<string, mixed> $legacyMethodology */
        $legacyMethodology = is_array($viewModel['methodology'] ?? null) ? $viewModel['methodology'] : [];
        $viewModel['calculation'] = [
            'summary' => self::CALCULATION_SUMMARY,
            'estimate_note' => self::ESTIMATE_NOTE,
            'coverage_note' => (string) ($calculation['coverage_note'] ?? $legacyMethodology['coverage_note'] ?? ''),
            'price_source' => (string) ($calculation['price_source'] ?? $legacyMethodology['price_source'] ?? 'Hourly electricity prices for Finland (FI)'),
            'assumptions' => $calculation['assumptions'] ?? $legacyMethodology['assumptions'] ?? [],
            'exclusions' => $calculation['exclusions'] ?? $legacyMethodology['exclusions'] ?? [],
        ];
        unset($viewModel['methodology'], $viewModel['appendix']);

        return $viewModel;
    }
}
