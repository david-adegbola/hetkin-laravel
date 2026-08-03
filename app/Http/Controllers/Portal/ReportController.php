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
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Throwable;

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
                'isEstimate' => $a->baseline->status->value !== 'LOCKED'
                    || str_starts_with($a->baseline->methodology_version, 'provisional-'),
                'current_annual_cost_cents' => $a->current_annual_cost_cents,
                'optimized_annual_cost_cents' => $a->optimized_annual_cost_cents,
                'estimated_annual_savings_cents' => $a->estimated_annual_savings_cents,
                'net_customer_savings_cents' => $a->net_customer_savings_cents,
                'fee_configured' => $organization->revenue_share_percent !== null,
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
        } catch (Throwable $e) {
            Log::error('[portal/reports] savings assessment generation failed', [
                'organization_id' => $currentOrganization->get()->id,
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return back()->withErrors([
                'assessment' => 'We could not generate the savings assessment. Please try again shortly.',
            ]);
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
            'vm' => $loader->viewModel($data),
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

        $vm = $loader->viewModel($data);

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

        $organizationSlug = Str::slug($organization->name);
        $filename = ($organizationSlug !== '' ? $organizationSlug.'-' : '')
            .'savings-assessment-'.$data->assessment->generated_at->format('Y-m-d').'.pdf';

        return $pdfService->download($vm, $filename)
            ->header('Cache-Control', 'no-store');
    }
}
