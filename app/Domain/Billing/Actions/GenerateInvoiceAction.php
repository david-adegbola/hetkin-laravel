<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\VerificationReviewStatus;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\VerificationRecord;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's generateInvoice() Server Action exactly
 * (src/app/admin/(protected)/invoices/actions.ts). The schema comment on
 * Invoice is explicit — "generated only from a VerificationRecord — never
 * entered manually" — so this is the only place an Invoice row gets
 * created, and it refuses anything that isn't LIVE_APPROVED. That status
 * only exists after a record has passed through both shadow-review steps
 * (ConfirmShadowReviewAction then ApproveLiveAction), so an invoice is
 * always at least three deliberate admin actions away from a raw
 * provisional number.
 *
 * Only writes the internal Invoice bookkeeping row — does NOT call any
 * payment provider. `stripe_invoice_id` stays null, matching v2 (a
 * schema-only placeholder; wiring actual payment collection needs an
 * explicit go-ahead this port hasn't received either).
 */
class GenerateInvoiceAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws BillingActionException
     */
    public function execute(string $verificationRecordId, AdminUser $adminUser): Invoice
    {
        // The `invoice` eager-load must explicitly bypass the scope too:
        // loaded WITH it active (and no CurrentOrganization bound in this
        // cross-tenant admin context), Eloquent would throw instead of
        // correctly resolving "no invoice yet" as null — see
        // MIGRATION_PLAN.md ADR-39.
        /** @var VerificationRecord $record */
        $record = VerificationRecord::withoutGlobalScope(OrganizationScope::class)
            ->with(['invoice' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])
            ->findOrFail($verificationRecordId);

        if ($record->review_status !== VerificationReviewStatus::LiveApproved) {
            throw new BillingActionException('Only LIVE_APPROVED verification records can be invoiced.');
        }

        if ($record->invoice) {
            return $record->invoice; // already generated — idempotent no-op
        }

        $organization = $record->organization;
        $baseFeeCents = $organization->base_fee_cents ?? 0;
        // Never bill a share of negative "savings" — a bad period doesn't
        // turn into a charge beyond the base fee.
        $revenueShareFraction = $organization->revenue_share_percent !== null
            ? ((float) $organization->revenue_share_percent) / 100
            : 0.0;
        $savingsShareCents = max(0, (int) round($record->verified_savings_cents * $revenueShareFraction));
        $totalCents = $baseFeeCents + $savingsShareCents;

        return DB::transaction(function () use ($record, $adminUser, $baseFeeCents, $savingsShareCents, $totalCents, $revenueShareFraction) {
            $invoice = Invoice::create([
                'organization_id' => $record->organization_id,
                'verification_record_id' => $record->id,
                'base_fee_cents' => $baseFeeCents,
                'savings_share_cents' => $savingsShareCents,
                'total_cents' => $totalCents,
                'status' => InvoiceStatus::Draft,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'invoice.generated',
                entityType: 'Invoice',
                entityId: $invoice->id,
                organizationId: $record->organization_id,
                metadata: [
                    'verificationRecordId' => $record->id,
                    'baseFeeCents' => $baseFeeCents,
                    'savingsShareCents' => $savingsShareCents,
                    'totalCents' => $totalCents,
                    'revenueShareFraction' => $revenueShareFraction,
                ],
            ));

            return $invoice->refresh();
        });
    }
}
