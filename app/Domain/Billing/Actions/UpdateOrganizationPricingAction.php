<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Audit\DTOs\AuditEvent;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Audit\Services\AuditLogService;
use App\Domain\Billing\Exceptions\BillingActionException;
use App\Domain\Organizations\Models\Organization;
use App\Models\AdminUser;
use Illuminate\Support\Facades\DB;

/**
 * Ports the Next.js app's updateOrganizationPricing() Server Action
 * exactly (src/app/admin/(protected)/organizations/[id]/actions.ts) — the
 * only place `base_fee_cents`/`revenue_share_percent` get set. These are
 * the inputs both GenerateSavingsAssessmentAction's hetkin_share_cents
 * calc and GenerateInvoiceAction's savings_share_cents calc read. Only
 * affects assessments/invoices generated after the change — historical
 * figures are immutable, since both are captured onto their own row at
 * generation time, not recomputed live.
 *
 * Takes raw strings (not a typed float/int) for the same reason v2's
 * inline validation does: an empty string means "clear this field"
 * (→ null), distinct from "0". Validation is hand-rolled here rather than
 * a Form Request rule set, matching v2's own inline-in-the-action
 * validation shape — there's no admin controller wired to this yet
 * (Stage 9), so a Form Request has nothing to attach to today.
 */
class UpdateOrganizationPricingAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @throws BillingActionException
     */
    public function execute(
        Organization $organization,
        AdminUser $adminUser,
        ?string $baseFeeEurRaw,
        ?string $revenueShareRaw,
    ): Organization {
        $baseFeeEurRaw = trim((string) $baseFeeEurRaw);
        $revenueShareRaw = trim((string) $revenueShareRaw);

        $baseFeeCents = $baseFeeEurRaw === '' ? null : (int) round(((float) $baseFeeEurRaw) * 100);
        $revenueSharePercent = $revenueShareRaw === '' ? null : (float) $revenueShareRaw;

        if ($baseFeeCents !== null && ($baseFeeEurRaw === 'NaN' || $baseFeeCents < 0 || ! is_numeric($baseFeeEurRaw))) {
            throw new BillingActionException('Base fee must be a non-negative number.');
        }
        if ($revenueSharePercent !== null && (! is_numeric($revenueShareRaw) || $revenueSharePercent < 0 || $revenueSharePercent > 100)) {
            throw new BillingActionException('Revenue share must be between 0 and 100.');
        }

        $fromBaseFeeCents = $organization->base_fee_cents;
        $fromRevenueSharePercent = $organization->revenue_share_percent;

        return DB::transaction(function () use ($organization, $adminUser, $baseFeeCents, $revenueSharePercent, $fromBaseFeeCents, $fromRevenueSharePercent) {
            $organization->update([
                'base_fee_cents' => $baseFeeCents,
                'revenue_share_percent' => $revenueSharePercent,
            ]);

            $this->auditLogService->record(new AuditEvent(
                actorType: AuditActorType::AdminUser,
                actorId: $adminUser->id,
                action: 'organization.pricing_changed',
                entityType: 'Organization',
                entityId: $organization->id,
                organizationId: $organization->id,
                metadata: [
                    'from' => ['baseFeeCents' => $fromBaseFeeCents, 'revenueSharePercent' => $fromRevenueSharePercent],
                    'to' => ['baseFeeCents' => $baseFeeCents, 'revenueSharePercent' => $revenueSharePercent],
                ],
            ));

            return $organization->refresh();
        });
    }
}
