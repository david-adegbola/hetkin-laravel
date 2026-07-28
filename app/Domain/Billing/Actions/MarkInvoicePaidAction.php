<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's markInvoicePaid() Server Action exactly
 * (src/app/admin/(protected)/invoices/actions.ts) — the entire "payment"
 * mechanism in this app: an admin records that payment was received
 * outside the app (bank transfer, etc.), there is no Stripe webhook.
 * `$paymentNote` is a free-text reference (bank statement line, transfer
 * ID) so a later audit-log reader can find the corroborating evidence,
 * since this system itself has no way to verify the payment actually
 * happened. Only accepts an ISSUED invoice — anything else throws.
 */
class MarkInvoicePaidAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws BillingActionException
     */
    public function execute(string $invoiceId, AdminUser $adminUser, ?string $paymentNote = null): Invoice
    {
        /** @var Invoice $invoice */
        $invoice = Invoice::withoutGlobalScope(OrganizationScope::class)->findOrFail($invoiceId);

        if ($invoice->status !== InvoiceStatus::Issued) {
            throw new BillingActionException('Only an ISSUED invoice can be marked paid.');
        }

        $note = trim((string) $paymentNote) !== '' ? trim($paymentNote) : null;
        $paidAt = now();

        return DB::transaction(function () use ($invoice, $adminUser, $note, $paidAt) {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $paidAt,
                'payment_note' => $note,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'invoice.marked_paid',
                entityType: 'Invoice',
                entityId: $invoice->id,
                organizationId: $invoice->organization_id,
                metadata: ['paymentNote' => $note],
            ));

            return $invoice->refresh();
        });
    }
}
