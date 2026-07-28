<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Actions\GenerateSavingsAssessmentAction;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\SavingsAssessment;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Billing\Pdf\SavingsAssessmentPdfService;
use App\Domain\Billing\Reports\SavingsReportLoader;
use App\Domain\Billing\Reports\SavingsReportViewModelBuilder;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/reports/{page.tsx,
 * [assessmentId]/{page.tsx,pdf/route.ts}} + actions.ts's
 * generateSavingsAssessment().
 */
class ReportController extends Controller
{
    public function index(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();

        $assessments = SavingsAssessment::query()
            ->where('organization_id', $organization->id)
            ->with('baseline')
            ->orderByDesc('generated_at')
            ->get();

        $verifications = VerificationRecord::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('period_start')
            ->get();

        return Inertia::render('Portal/Reports', [
            'assessments' => $assessments->map(fn (SavingsAssessment $a) => [
                'id' => $a->id,
                'generated_at' => $a->generated_at->toIso8601String(),
                'status' => $a->status->value,
                'provisional' => $a->baseline->status->value !== 'LOCKED'
                    || str_starts_with($a->baseline->methodology_version, 'provisional-'),
                'current_annual_cost_cents' => $a->current_annual_cost_cents,
                'optimized_annual_cost_cents' => $a->optimized_annual_cost_cents,
                'net_customer_savings_cents' => $a->net_customer_savings_cents,
            ]),
            'verifications' => $verifications->map(fn (VerificationRecord $v) => [
                'id' => $v->id,
                'period_start' => $v->period_start->toIso8601String(),
                'period_end' => $v->period_end->toIso8601String(),
                'verified_savings_cents' => $v->verified_savings_cents,
                'review_status' => $v->review_status->value,
            ]),
        ]);
    }

    public function generate(GenerateSavingsAssessmentAction $action, CurrentOrganization $currentOrganization): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        try {
            $action->execute($currentOrganization->get(), $user);
        } catch (BillingActionException $e) {
            return back()->withErrors(['assessment' => $e->getMessage()]);
        }

        return back()->with('status', 'assessment-generated');
    }

    public function show(string $assessment, SavingsReportLoader $loader, CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();
        $data = $loader->load($assessment, $organization->id);

        abort_if($data === null, 404);

        return Inertia::render('Portal/ReportDetail', [
            'assessment_id' => $data->assessment->id,
            'vm' => SavingsReportViewModelBuilder::build($data),
        ]);
    }

    public function pdf(
        string $assessment,
        SavingsReportLoader $loader,
        SavingsAssessmentPdfService $pdfService,
        AuditLogService $auditLogService,
        CurrentOrganization $currentOrganization,
    ): HttpResponse {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        $organization = $currentOrganization->get();

        $data = $loader->load($assessment, $organization->id);
        abort_if($data === null, 404);

        $vm = SavingsReportViewModelBuilder::build($data);

        DB::transaction(function () use ($auditLogService, $user, $organization, $data) {
            $auditLogService->record(new AuditEvent(
                actorType: AuditActorType::User,
                actorId: $user->id,
                action: 'savings_assessment.pdf_downloaded',
                entityType: 'SavingsAssessment',
                entityId: $data->assessment->id,
                organizationId: $organization->id,
            ));
        });

        $filename = 'hetkin-savings-assessment-'.$data->assessment->generated_at->format('Y-m-d').'.pdf';

        return $pdfService->download($vm, $filename)
            ->header('Cache-Control', 'no-store');
    }
}
