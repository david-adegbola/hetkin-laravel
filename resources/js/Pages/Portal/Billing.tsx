import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { formatEurCents } from '@/lib/format';
import { Head } from '@inertiajs/react';
import { ReactElement } from 'react';

interface InvoiceRow {
    id: string;
    date: string;
    base_fee_cents: number;
    savings_share_cents: number;
    total_cents: number;
    status: string;
}

interface Pricing {
    base_fee_cents: number;
    revenue_share_percent: number;
}

function statusBadgeVariant(status: string): 'cheap' | 'expensive' | 'neutral' {
    if (status === 'PAID') return 'cheap';
    if (status === 'DISPUTED') return 'expensive';
    return 'neutral';
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/billing/page.tsx.
 */
export default function Billing({
    pricing,
    invoices,
}: {
    pricing: Pricing | null;
    invoices: InvoiceRow[];
}) {
    return (
        <>
            <Head title="Billing" />
            <PortalPageHeader title="Billing" />
            <div className="text-text-secondary mb-4 text-sm">
                {pricing ? (
                    <>
                        Base fee: {formatEurCents(pricing.base_fee_cents)}/mo ·
                        Revenue share: {pricing.revenue_share_percent}%
                    </>
                ) : (
                    "Pricing terms are confirmed with you before your pilot's first invoice."
                )}
            </div>

            {invoices.length === 0 ? (
                <Card className="text-text-secondary py-12 text-center text-sm">
                    No invoices yet. Every invoice here is generated only from a
                    verified, reviewed VerificationRecord — never entered
                    manually.
                </Card>
            ) : (
                <Card>
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="py-2 pr-4">Date</th>
                                <th className="py-2 pr-4">Base fee</th>
                                <th className="py-2 pr-4">Savings share</th>
                                <th className="py-2 pr-4">Total</th>
                                <th className="py-2 pr-4">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((inv) => (
                                <tr
                                    key={inv.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="py-2 pr-4">
                                        {new Date(inv.date).toLocaleDateString(
                                            'en-GB',
                                        )}
                                    </td>
                                    <td className="py-2 pr-4 tabular-nums">
                                        {formatEurCents(inv.base_fee_cents)}
                                    </td>
                                    <td className="py-2 pr-4 tabular-nums">
                                        {formatEurCents(
                                            inv.savings_share_cents,
                                        )}
                                    </td>
                                    <td className="py-2 pr-4 font-semibold tabular-nums">
                                        {formatEurCents(inv.total_cents)}
                                    </td>
                                    <td className="py-2 pr-4">
                                        <Badge
                                            variant={statusBadgeVariant(
                                                inv.status,
                                            )}
                                            showGlyph={false}
                                        >
                                            {inv.status.toLowerCase()}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </Card>
            )}
        </>
    );
}

Billing.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
