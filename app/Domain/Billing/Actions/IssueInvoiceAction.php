<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Analytics\Services\PostHogService;
use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's issueInvoice() Server Action exactly
 * (src/app/admin/(protected)/invoices/actions.ts). DRAFT → ISSUED is a
 * separate, deliberate step from generation, same reasoning as baseline
 * locking and verification review: computing a number and putting it in
 * front of a customer are different levels of commitment. Only accepts a
 * DRAFT invoice (a silent no-op otherwise). `due_at` defaults to 14 days
 * out — a placeholder; actual payment terms haven't been decided beyond
 * the base-fee/revenue-share structure itself.
 */
class IssueInvoiceAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly PostHogService $postHog,
    ) {}

    public function execute(string $invoiceId, AdminUser $adminUser): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::withoutGlobalScope(OrganizationScope::class)->findOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::Draft) {
            return $invoice;
        }

        $issuedAt = now();
        $dueAt = $issuedAt->copy()->addDays(14);

        $invoice = DB::transaction(function () use ($invoice, $adminUser, $issuedAt, $dueAt) {
            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'issued_at' => $issuedAt,
                'due_at' => $dueAt,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'invoice.issued',
                entityType: 'Invoice',
                entityId: $invoice->id,
                organizationId: $invoice->organization_id,
                metadata: ['dueAt' => $dueAt->toIso8601String()],
            ));

            return $invoice->refresh();
        });

        // Phase 1 §8 funnel: closest proxy to "verified savings reaching
        // the customer as a real bill" — org-scoped, not user-scoped,
        // since this is an admin action.
        $this->postHog->capture('invoice_issued', $invoice->organization_id, [
            'organizationId' => $invoice->organization_id,
            'invoiceId' => $invoice->id,
            'totalCents' => $invoice->total_cents,
        ]);

        return $invoice;
    }
}
