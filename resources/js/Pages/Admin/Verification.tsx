import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatEurCents } from '@/lib/format';
import { useAdminBasePath } from '@/lib/subdomain';
import type { AdminRole, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactElement } from 'react';

interface VerificationRow {
    id: string;
    organization_id: string;
    organization_name: string;
    period_start: string;
    period_end: string;
    methodology_version: string;
    baseline_cost_cents: number;
    actual_cost_cents: number;
    verified_savings_cents: number;
    variance_percent: number;
    variance_flagged: boolean;
    review_status: string;
    reviewed_at: string | null;
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'LIVE_APPROVED'
            ? 'cheap'
            : status === 'DISPUTED'
              ? 'expensive'
              : status === 'SHADOW_CONFIRMED'
                ? 'navy'
                : 'mid';
    return (
        <Badge variant={variant} showGlyph={false}>
            {status.toLowerCase().replace(/_/g, ' ')}
        </Badge>
    );
}

function RowActions({
    record,
    base,
    canReview,
    canReopen,
}: {
    record: VerificationRow;
    base: string;
    canReview: boolean;
    canReopen: boolean;
}) {
    const linkClass =
        'rounded-control px-3 py-1.5 text-xs font-semibold hover:opacity-90';

    if (record.review_status === 'SHADOW_PENDING_REVIEW' && canReview) {
        return (
            <div className="flex gap-2">
                <Link
                    href={`${base}/verification/${record.id}/confirm-shadow-review`}
                    method="post"
                    as="button"
                    className={`bg-success text-white ${linkClass}`}
                >
                    Confirm
                </Link>
                <Link
                    href={`${base}/verification/${record.id}/dispute`}
                    method="post"
                    as="button"
                    className={`border-border text-text-secondary hover:border-error hover:text-error border ${linkClass}`}
                >
                    Dispute
                </Link>
            </div>
        );
    }

    if (record.review_status === 'SHADOW_CONFIRMED' && canReview) {
        return (
            <div className="flex gap-2">
                <Link
                    href={`${base}/verification/${record.id}/approve-live`}
                    method="post"
                    as="button"
                    className={`bg-success text-white ${linkClass}`}
                >
                    Approve live
                </Link>
                <Link
                    href={`${base}/verification/${record.id}/dispute`}
                    method="post"
                    as="button"
                    className={`border-border text-text-secondary hover:border-error hover:text-error border ${linkClass}`}
                >
                    Dispute
                </Link>
            </div>
        );
    }

    if (record.review_status === 'LIVE_APPROVED') {
        return (
            <span className="text-text-secondary text-xs">
                {record.reviewed_at
                    ? new Date(record.reviewed_at).toLocaleDateString('en-GB')
                    : '—'}
            </span>
        );
    }

    if (record.review_status === 'DISPUTED' && canReopen) {
        return (
            <Link
                href={`${base}/verification/${record.id}/reopen`}
                method="post"
                as="button"
                className={`border-border text-text-secondary hover:border-accent hover:text-accent border ${linkClass}`}
            >
                Reopen
            </Link>
        );
    }

    return null;
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/verification/
 * page.tsx. Filterable by organization_id (query string). Confirm/approve/
 * dispute are `admin.role:ADMIN,VERIFICATION_ANALYST`; reopen is narrower —
 * `admin.role:ADMIN` only.
 */
export default function Verification({
    records,
    organization_id,
}: {
    records: VerificationRow[];
    organization_id: string | null;
}) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const role = auth.admin?.role as AdminRole | undefined;
    const canReview = (
        ['ADMIN', 'VERIFICATION_ANALYST'] as AdminRole[]
    ).includes(role as AdminRole);
    const canReopen = role === 'ADMIN';

    const pendingCount = records.filter(
        (r) => r.review_status === 'SHADOW_PENDING_REVIEW',
    ).length;
    const flaggedCount = records.filter(
        (r) => r.variance_flagged && r.review_status !== 'LIVE_APPROVED',
    ).length;

    return (
        <div>
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Verification
                </h1>
                <p className="text-text-secondary text-sm">
                    {pendingCount} pending review · {flaggedCount} flagged for
                    variance
                    {organization_id && (
                        <>
                            {' · '}
                            <Link
                                href={`${base}/verification`}
                                className="text-accent hover:underline"
                            >
                                clear organization filter
                            </Link>
                        </>
                    )}
                </p>
            </div>

            {records.length === 0 ? (
                <div className="border-border bg-background rounded-card text-text-secondary border p-8 text-center text-sm">
                    No verification records yet.
                </div>
            ) : (
                <div className="border-border bg-background rounded-card overflow-x-auto border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="px-4 py-3">Organization</th>
                                <th className="px-4 py-3">Period</th>
                                <th className="px-4 py-3">Baseline</th>
                                <th className="px-4 py-3">Actual</th>
                                <th className="px-4 py-3">Verified savings</th>
                                <th className="px-4 py-3">Variance</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {records.map((r) => (
                                <tr
                                    key={r.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`${base}/organizations/${r.organization_id}`}
                                            className="text-accent font-medium hover:underline"
                                        >
                                            {r.organization_name}
                                        </Link>
                                        {r.methodology_version.startsWith(
                                            'provisional-',
                                        ) && (
                                            <Badge
                                                variant="mid"
                                                showGlyph={false}
                                                className="ml-2"
                                            >
                                                provisional
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            r.period_start,
                                        ).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatEurCents(r.baseline_cost_cents)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatEurCents(r.actual_cost_cents)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {formatEurCents(
                                            r.verified_savings_cents,
                                        )}
                                    </td>
                                    <td
                                        className={`px-4 py-3 ${r.variance_flagged ? 'text-error font-semibold' : ''}`}
                                    >
                                        {r.variance_percent}%
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={r.review_status} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <RowActions
                                            record={r}
                                            base={base}
                                            canReview={canReview}
                                            canReopen={canReopen}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

Verification.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
