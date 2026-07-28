<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Actions\ApproveLiveAction;
use App\Domain\Billing\Actions\ConfirmShadowReviewAction;
use App\Domain\Billing\Actions\DisputeRecordAction;
use App\Domain\Billing\Actions\ReopenDisputedAction;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/verification/
 * {page.tsx,actions.ts}. The index has no role gate. Three of the four
 * transitions are `admin.role:ADMIN,VERIFICATION_ANALYST`; `reopen` is
 * narrower — `admin.role:ADMIN` only, matching v2's
 * `requireAdminRole(["ADMIN"])` for `reopenDisputed` (v2's comment:
 * "whatever made it disputed in the first place deserves a fresh look").
 */
class VerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $organizationId = $request->string('organization_id')->toString() ?: null;

        $records = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId))
            ->with('organization')
            ->orderBy('review_status')
            ->orderByDesc('period_start')
            ->get();

        return Inertia::render('Admin/Verification', [
            'records' => $records->map(fn (VerificationRecord $record) => [
                'id' => $record->id,
                'organization_id' => $record->organization_id,
                'organization_name' => $record->organization->name,
                'period_start' => $record->period_start->toIso8601String(),
                'period_end' => $record->period_end->toIso8601String(),
                'methodology_version' => $record->methodology_version,
                'baseline_cost_cents' => $record->baseline_cost_cents,
                'actual_cost_cents' => $record->actual_cost_cents,
                'verified_savings_cents' => $record->verified_savings_cents,
                'variance_percent' => (float) $record->variance_percent,
                'variance_flagged' => $record->variance_flagged,
                'review_status' => $record->review_status->value,
                'reviewed_at' => $record->reviewed_at?->toIso8601String(),
            ]),
            'organization_id' => $organizationId,
        ]);
    }

    public function confirmShadowReview(string $record, ConfirmShadowReviewAction $action): RedirectResponse
    {
        $action->execute($record, $this->admin());

        return back();
    }

    public function approveLive(string $record, ApproveLiveAction $action): RedirectResponse
    {
        $action->execute($record, $this->admin());

        return back();
    }

    public function dispute(string $record, DisputeRecordAction $action): RedirectResponse
    {
        $action->execute($record, $this->admin());

        return back();
    }

    public function reopen(string $record, ReopenDisputedAction $action): RedirectResponse
    {
        $action->execute($record, $this->admin());

        return back();
    }

    private function admin(): AdminUser
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
