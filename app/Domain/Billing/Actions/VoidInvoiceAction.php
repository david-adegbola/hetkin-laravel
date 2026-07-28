<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's voidInvoice() Server Action exactly
 * (src/app/admin/(protected)/invoices/actions.ts) — "escape hatch for a
 * generated invoice that shouldn't stand — e.g. the underlying
 * VerificationRecord gets disputed after the fact." Accepts anything
 * except PAID or already-VOID (a silent no-op for those two). Voiding does
 * NOT automatically flip the VerificationRecord back to DISPUTED — that's
 * a separate manual action (App\Domain\Billing\Actions\DisputeRecordAction).
 */
class VoidInvoiceAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(string $invoiceId, AdminUser $adminUser): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::withoutGlobalScope(OrganizationScope::class)->findOrFail($invoiceId);

        if (in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true)) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $adminUser) {
            $invoice->update(['status' => InvoiceStatus::Void]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'invoice.voided',
                entityType: 'Invoice',
                entityId: $invoice->id,
                organizationId: $invoice->organization_id,
            ));

            return $invoice->refresh();
        });
    }
}
