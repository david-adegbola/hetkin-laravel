import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardTitle } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatEurCents } from '@/lib/format';
import { useAdminBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ReactElement, useState } from 'react';

interface InvoiceableRecord {
    id: string;
    organization_id: string;
    organization_name: string;
    period_start: string;
    period_end: string;
    verified_savings_cents: number;
    base_fee_cents: number | null;
    revenue_share_percent: number | null;
}

interface InvoiceRow {
    id: string;
    organization_id: string;
    organization_name: string;
    period_start: string;
    period_end: string;
    base_fee_cents: number;
    savings_share_cents: number;
    total_cents: number;
    status: string;
    issued_at: string | null;
    due_at: string | null;
    paid_at: string | null;
    payment_note: string | null;
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'PAID'
            ? 'cheap'
            : status === 'DISPUTED' || status === 'VOID'
              ? 'expensive'
              : status === 'ISSUED'
                ? 'navy'
                : 'neutral';
    return (
        <Badge variant={variant} showGlyph={false}>
            {status.toLowerCase()}
        </Badge>
    );
}

function MarkPaidControl({
    base,
    invoice,
}: {
    base: string;
    invoice: InvoiceRow;
}) {
    const [note, setNote] = useState('');
    const [processing, setProcessing] = useState(false);

    function submit() {
        setProcessing(true);
        router.post(
            `${base}/invoices/${invoice.id}/mark-paid`,
            { payment_note: note },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    return (
        <div className="flex items-center gap-2">
            <Input
                placeholder="Payment note (optional)"
                value={note}
                onChange={(e) => setNote(e.target.value)}
                className="h-9 w-40 text-xs"
            />
            <Button
                type="button"
                size="sm"
                onClick={submit}
                disabled={processing}
            >
                {processing ? 'Saving…' : 'Mark paid'}
            </Button>
        </div>
    );
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/invoices/page.tsx.
 * All four mutations are `admin.role:ADMIN` only — no FINANCE role exists
 * in v2, and money-moving bookkeeping isn't shared with any other role.
 */
export default function Invoices({
    invoiceable_records,
    invoices,
}: {
    invoiceable_records: InvoiceableRecord[];
    invoices: InvoiceRow[];
}) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const isAdmin = auth.admin?.role === 'ADMIN';

    return (
        <div>
            <Head title="Invoices" />
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Invoices
                </h1>
                <p className="text-text-secondary text-sm">
                    {invoiceable_records.length} ready to invoice ·{' '}
                    {invoices.length} total
                </p>
            </div>

            <Card className="mb-6">
                <CardTitle className="mb-3">Ready to invoice</CardTitle>
                {invoiceable_records.length === 0 ? (
                    <p className="text-text-secondary text-sm">
                        Nothing ready — invoices are only generated from
                        LIVE_APPROVED verification records.
                    </p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="py-2 pr-4">Organization</th>
                                <th className="py-2 pr-4">Period</th>
                                <th className="py-2 pr-4">Verified savings</th>
                                <th className="py-2 pr-4">Base fee</th>
                                <th className="py-2 pr-4">Revenue share</th>
                                {isAdmin && (
                                    <th className="py-2 pr-4">Actions</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {invoiceable_records.map((r) => (
                                <tr
                                    key={r.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="py-2 pr-4">
                                        <Link
                                            href={`${base}/organizations/${r.organization_id}`}
                                            className="text-accent font-medium hover:underline"
                                        >
                                            {r.organization_name}
                                        </Link>
                                    </td>
                                    <td className="py-2 pr-4">
                                        {new Date(
                                            r.period_start,
                                        ).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="py-2 pr-4">
                                        {formatEurCents(
                                            r.verified_savings_cents,
                                        )}
                                    </td>
                                    <td className="py-2 pr-4">
                                        {r.base_fee_cents !== null
                                            ? formatEurCents(r.base_fee_cents)
                                            : '—'}
                                    </td>
                                    <td className="py-2 pr-4">
                                        {r.revenue_share_percent !== null
                                            ? `${r.revenue_share_percent}%`
                                            : '—'}
                                    </td>
                                    {isAdmin && (
                                        <td className="py-2 pr-4">
                                            <Link
                                                href={`${base}/invoices/generate/${r.id}`}
                                                method="post"
                                                as="button"
                                                className="bg-accent rounded-control px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                            >
                                                Generate invoice
                                            </Link>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            <Card>
                <CardTitle className="mb-3">All invoices</CardTitle>
                {invoices.length === 0 ? (
                    <p className="text-text-secondary text-sm">
                        No invoices yet.
                    </p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="py-2 pr-4">Organization</th>
                                <th className="py-2 pr-4">Total</th>
                                <th className="py-2 pr-4">Status</th>
                                {isAdmin && (
                                    <th className="py-2 pr-4">Actions</th>
                                )}
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((inv) => (
                                <tr
                                    key={inv.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="py-2 pr-4">
                                        <Link
                                            href={`${base}/organizations/${inv.organization_id}`}
                                            className="text-accent font-medium hover:underline"
                                        >
                                            {inv.organization_name}
                                        </Link>
                                    </td>
                                    <td className="py-2 pr-4 font-semibold tabular-nums">
                                        {formatEurCents(inv.total_cents)}
                                    </td>
                                    <td className="py-2 pr-4">
                                        <StatusBadge status={inv.status} />
                                    </td>
                                    {isAdmin && (
                                        <td className="py-2 pr-4">
                                            {inv.status === 'DRAFT' && (
                                                <div className="flex gap-2">
                                                    <Link
                                                        href={`${base}/invoices/${inv.id}/issue`}
                                                        method="post"
                                                        as="button"
                                                        className="bg-accent rounded-control px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                                    >
                                                        Issue
                                                    </Link>
                                                    <Link
                                                        href={`${base}/invoices/${inv.id}/void`}
                                                        method="post"
                                                        as="button"
                                                        className="border-border text-text-secondary hover:border-error hover:text-error rounded-control border px-3 py-1.5 text-xs font-semibold"
                                                    >
                                                        Void
                                                    </Link>
                                                </div>
                                            )}
                                            {inv.status === 'ISSUED' && (
                                                <div className="flex items-center gap-2">
                                                    <MarkPaidControl
                                                        base={base}
                                                        invoice={inv}
                                                    />
                                                    <Link
                                                        href={`${base}/invoices/${inv.id}/void`}
                                                        method="post"
                                                        as="button"
                                                        className="border-border text-text-secondary hover:border-error hover:text-error rounded-control border px-3 py-1.5 text-xs font-semibold"
                                                    >
                                                        Void
                                                    </Link>
                                                </div>
                                            )}
                                            {inv.status === 'PAID' && (
                                                <span className="text-text-secondary text-xs">
                                                    {inv.paid_at
                                                        ? new Date(
                                                              inv.paid_at,
                                                          ).toLocaleDateString(
                                                              'en-GB',
                                                          )
                                                        : '—'}
                                                    {inv.payment_note &&
                                                        ` · ${inv.payment_note}`}
                                                </span>
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
        </div>
    );
}

Invoices.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
