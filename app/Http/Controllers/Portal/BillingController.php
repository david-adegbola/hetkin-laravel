<?php

namespace App\Http\Controllers\Portal;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Organizations\CurrentOrganization;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** Mirrors the Next.js app's src/app/(portal)/dashboard/billing/page.tsx. */
class BillingController extends Controller
{
    public function index(CurrentOrganization $currentOrganization): Response
    {
        $organization = $currentOrganization->get();

        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Portal/Billing', [
            'pricing' => ($organization->base_fee_cents !== null && $organization->revenue_share_percent !== null)
                ? [
                    'base_fee_cents' => $organization->base_fee_cents,
                    'revenue_share_percent' => (float) $organization->revenue_share_percent,
                ]
                : null,
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'date' => ($invoice->issued_at ?? $invoice->created_at)->toIso8601String(),
                'base_fee_cents' => $invoice->base_fee_cents,
                'savings_share_cents' => $invoice->savings_share_cents,
                'total_cents' => $invoice->total_cents,
                'status' => $invoice->status->value,
            ]),
        ]);
    }
}
