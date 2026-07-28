import { Badge } from '@/Components/ui/Badge';
import { Card, CardTitle } from '@/Components/ui/Card';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatEurCents } from '@/lib/format';
import { useAdminBasePath } from '@/lib/subdomain';
import { Link } from '@inertiajs/react';
import { ReactElement, ReactNode } from 'react';

interface AuditChain {
    valid: boolean;
    brokenAtEntryId: string | null;
}

interface OverviewProps {
    organizations: { total: number; by_status: Record<string, number> };
    pending_pilot_applications: number;
    baselines: { locked: number; total: number };
    verification: { pending_review: number; flagged_unresolved: number };
    invoices: { draft: number; outstanding_cents: number };
    pricing_freshness: { hours_stale: number | null; stale: boolean };
    jobs: {
        any_failed: boolean;
        last_runs: Array<{
            job_name: string;
            status: string;
            started_at: string;
        }>;
    };
    audit_chain: AuditChain;
    active_admin_count: number;
    unread_alert_count: number;
    needs_attention: boolean;
}

function HealthCard({
    label,
    value,
    tone = 'neutral',
    href,
}: {
    label: string;
    value: ReactNode;
    tone?: 'neutral' | 'cheap' | 'expensive';
    href?: string;
}) {
    const toneClass =
        tone === 'cheap'
            ? 'text-success'
            : tone === 'expensive'
              ? 'text-error'
              : 'text-primary';

    const content = (
        <>
            <div className="text-text-secondary text-xs font-semibold uppercase">
                {label}
            </div>
            <div
                className={`font-display mt-1 text-2xl font-semibold ${toneClass}`}
            >
                {value}
            </div>
        </>
    );

    if (href) {
        return (
            <Link
                href={href}
                className="rounded-card border-border bg-surface block border p-4 shadow-sm hover:opacity-90"
            >
                {content}
            </Link>
        );
    }

    return (
        <div className="rounded-card border-border bg-surface border p-4 shadow-sm">
            {content}
        </div>
    );
}

/**
 * Mirrors the Next.js app's admin root, src/app/admin/(protected)/
 * page.tsx (`AdminOverviewPage`) in full, including the unread-Alert count
 * (Stage 10).
 */
export default function Overview({
    organizations,
    pending_pilot_applications,
    baselines,
    verification,
    invoices,
    pricing_freshness,
    jobs,
    audit_chain,
    active_admin_count,
    unread_alert_count,
    needs_attention,
}: OverviewProps) {
    const base = useAdminBasePath();

    return (
        <div>
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Overview
                </h1>
                <p className="text-text-secondary text-sm">
                    System health at a glance.
                </p>
            </div>

            {needs_attention && (
                <div className="bg-expensive-100 text-primary rounded-card border-error/30 mb-6 border p-4 text-sm">
                    <strong className="block font-semibold">
                        Needs attention
                    </strong>
                    <ul className="mt-1 list-inside list-disc space-y-1">
                        {pricing_freshness.stale && (
                            <li>
                                Electricity price data is stale
                                {pricing_freshness.hours_stale !== null
                                    ? ` (${pricing_freshness.hours_stale}h old)`
                                    : ' (no data)'}
                                .
                            </li>
                        )}
                        {jobs.any_failed && (
                            <li>
                                A background job has failed —{' '}
                                <Link
                                    href={`${base}/jobs`}
                                    className="underline"
                                >
                                    see Jobs
                                </Link>
                                .
                            </li>
                        )}
                        {!audit_chain.valid && (
                            <li>
                                Audit log hash chain is BROKEN at entry{' '}
                                {audit_chain.brokenAtEntryId} —{' '}
                                <Link
                                    href={`${base}/audit-log`}
                                    className="underline"
                                >
                                    see Audit Log
                                </Link>
                                .
                            </li>
                        )}
                        {verification.pending_review > 0 && (
                            <li>
                                {verification.pending_review} verification
                                record(s) awaiting review —{' '}
                                <Link
                                    href={`${base}/verification`}
                                    className="underline"
                                >
                                    see Verification
                                </Link>
                                .
                            </li>
                        )}
                    </ul>
                </div>
            )}

            <CardTitle className="mb-3">Pipeline</CardTitle>
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <HealthCard
                    label="Pending pilot applications"
                    value={pending_pilot_applications}
                    href={`${base}/pilots`}
                />
                <HealthCard
                    label="Organizations"
                    value={organizations.total}
                    href={`${base}/organizations`}
                />
                <HealthCard
                    label="Baselines locked"
                    value={`${baselines.locked} / ${baselines.total}`}
                    href={`${base}/baselines`}
                />
                <HealthCard
                    label="Unread alerts"
                    value={unread_alert_count}
                    tone={unread_alert_count > 0 ? 'expensive' : 'cheap'}
                    href={`${base}/organizations`}
                />
            </div>

            <CardTitle className="mb-3">Verification &amp; billing</CardTitle>
            <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <HealthCard
                    label="Awaiting review"
                    value={verification.pending_review}
                    tone={
                        verification.pending_review > 0 ? 'expensive' : 'cheap'
                    }
                    href={`${base}/verification`}
                />
                <HealthCard
                    label="Flagged for variance"
                    value={verification.flagged_unresolved}
                    tone={
                        verification.flagged_unresolved > 0
                            ? 'expensive'
                            : 'cheap'
                    }
                    href={`${base}/verification`}
                />
                <HealthCard
                    label="Draft invoices"
                    value={invoices.draft}
                    href={`${base}/invoices`}
                />
                <HealthCard
                    label="Outstanding"
                    value={formatEurCents(invoices.outstanding_cents)}
                    href={`${base}/invoices`}
                />
            </div>

            <CardTitle className="mb-3">Platform</CardTitle>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <HealthCard
                    label="Price data"
                    value={pricing_freshness.stale ? 'Stale' : 'Fresh'}
                    tone={pricing_freshness.stale ? 'expensive' : 'cheap'}
                />
                <HealthCard
                    label="Background jobs"
                    value={jobs.any_failed ? 'Failure' : 'OK'}
                    tone={jobs.any_failed ? 'expensive' : 'cheap'}
                    href={`${base}/jobs`}
                />
                <HealthCard
                    label="Audit chain"
                    value={audit_chain.valid ? 'Intact' : 'BROKEN'}
                    tone={audit_chain.valid ? 'cheap' : 'expensive'}
                    href={`${base}/audit-log`}
                />
                <HealthCard
                    label="Active admins"
                    value={active_admin_count}
                    href={`${base}/team`}
                />
            </div>

            {jobs.last_runs.length > 0 && (
                <Card className="mt-6">
                    <CardTitle className="mb-3">Recent job runs</CardTitle>
                    <div className="flex flex-col gap-2">
                        {jobs.last_runs.map((run) => (
                            <div
                                key={run.job_name}
                                className="flex items-center justify-between text-sm"
                            >
                                <span className="font-mono">
                                    {run.job_name}
                                </span>
                                <span className="text-text-secondary">
                                    {new Date(run.started_at).toLocaleString(
                                        'en-GB',
                                    )}
                                </span>
                                <Badge
                                    variant={
                                        run.status === 'SUCCEEDED'
                                            ? 'cheap'
                                            : run.status === 'FAILED'
                                              ? 'expensive'
                                              : 'neutral'
                                    }
                                    showGlyph={false}
                                >
                                    {run.status.toLowerCase()}
                                </Badge>
                            </div>
                        ))}
                    </div>
                </Card>
            )}
        </div>
    );
}

Overview.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
