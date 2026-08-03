<?php

namespace App\Domain\Billing\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Renders the same view-model App\Domain\Billing\Reports\
 * SavingsReportViewModelBuilder produces for the web report page into a
 * downloadable PDF, via resources/views/pdf/savings-assessment.blade.php.
 *
 * v2's src/lib/pdf/savings-assessment-pdf.ts hand-draws every page with
 * pdf-lib at fixed pt coordinates (its own WinAnsi-only font, no headless
 * browser). This port renders an HTML/CSS view through dompdf instead —
 * see MIGRATION_PLAN.md's Stage 7 ADR for why: the customer-visible
 * contract is "the numbers in this downloaded PDF are correct and match
 * the web report," not "these particular pixels are in these particular
 * places." dompdf also supports full UTF-8 natively, so the WinAnsi
 * sanitization pdf-lib needed has no equivalent here — nothing to port.
 *
 * Always regenerated on request, never cached/stored — matching v2
 * exactly (SavingsAssessment.report_pdf_url stays permanently null; see
 * App\Http\Controllers\Portal\ReportController).
 */
class SavingsAssessmentPdfService
{
    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function render(array $viewModel): string
    {
        return Pdf::loadView('pdf.savings-assessment', ['vm' => $viewModel])
            ->setPaper('a4', 'portrait')
            ->setOption('isPhpEnabled', true)
            ->output();
    }

    /**
     * @param  array<string, mixed>  $viewModel
     */
    public function download(array $viewModel, string $filename): Response
    {
        return Pdf::loadView('pdf.savings-assessment', ['vm' => $viewModel])
            ->setPaper('a4', 'portrait')
            ->setOption('isPhpEnabled', true)
            ->download($filename);
    }
}
