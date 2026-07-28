<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Billing\Actions\GenerateInvoiceAction;
use App\Domain\Billing\Actions\IssueInvoiceAction;
use App\Domain\Billing\Actions\MarkInvoicePaidAction;
use App\Domain\Billing\Actions\VoidInvoiceAction;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Models\Invoice;
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
 * Mirrors the Next.js app's src/app/admin/(protected)/invoices/
 * {page.tsx,actions.ts}. The index has no role gate. All four mutations
 * are `admin.role:ADMIN` only — v2 has no FINANCE role, and money-moving
 * bookkeeping isn't shared with VERIFICATION_ANALYST or any other role.
 */
class InvoiceController extends Controller
{
    public function index(): Response
    {
        // No `invoice_id` column on VerificationRecord (Invoice holds the
        // FK the other way) — excluding already-invoiced records by
        // pulling Invoice's own verification_record_ids avoids a
        // whereDoesntHave() relation query that would otherwise need its
        // own OrganizationScope bypass on the related Invoice query too.
        $invoicedRecordIds = Invoice::withoutGlobalScope(OrganizationScope::class)->pluck('verification_record_id');

        $invoiceableRecords = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->where('review_status', VerificationReviewStatus::LiveApproved)
            ->whereNotIn('id', $invoicedRecordIds)
            ->with('organization')
            ->orderByDesc('period_start')
            ->get();

        $invoices = Invoice::withoutGlobalScope(OrganizationScope::class)
            ->with([
                'organization',
                // VerificationRecord is BelongsToOrganization-scoped too —
                // the eager-load query against it needs the same explicit
                // bypass as the top-level Invoice query above.
                'verificationRecord' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class),
            ])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Invoices', [
            'invoiceable_records' => $invoiceableRecords->map(fn (VerificationRecord $record) => [
                'id' => $record->id,
                'organization_id' => $record->organization_id,
                'organization_name' => $record->organization->name,
                'period_start' => $record->period_start->toIso8601String(),
                'period_end' => $record->period_end->toIso8601String(),
                'verified_savings_cents' => $record->verified_savings_cents,
                'base_fee_cents' => $record->organization->base_fee_cents,
                'revenue_share_percent' => $record->organization->revenue_share_percent !== null
                    ? (float) $record->organization->revenue_share_percent
                    : null,
            ]),
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'organization_id' => $invoice->organization_id,
                'organization_name' => $invoice->organization->name,
                'period_start' => $invoice->verificationRecord->period_start->toIso8601String(),
                'period_end' => $invoice->verificationRecord->period_end->toIso8601String(),
                'base_fee_cents' => $invoice->base_fee_cents,
                'savings_share_cents' => $invoice->savings_share_cents,
                'total_cents' => $invoice->total_cents,
                'status' => $invoice->status->value,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'due_at' => $invoice->due_at?->toIso8601String(),
                'paid_at' => $invoice->paid_at?->toIso8601String(),
                'payment_note' => $invoice->payment_note,
            ]),
        ]);
    }

    public function generate(string $record, GenerateInvoiceAction $action): RedirectResponse
    {
        $action->execute($record, $this->admin());

        return back();
    }

    public function issue(string $invoice, IssueInvoiceAction $action): RedirectResponse
    {
        $action->execute($invoice, $this->admin());

        return back();
    }

    public function void(string $invoice, VoidInvoiceAction $action): RedirectResponse
    {
        $action->execute($invoice, $this->admin());

        return back();
    }

    public function markPaid(Request $request, string $invoice, MarkInvoicePaidAction $action): RedirectResponse
    {
        $action->execute($invoice, $this->admin(), $request->input('payment_note'));

        return back();
    }

    private function admin(): AdminUser
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
