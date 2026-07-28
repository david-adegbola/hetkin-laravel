import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatEurCents } from '@/lib/format';
import { useAdminBasePath } from '@/lib/subdomain';
import type { AdminRole, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactElement } from 'react';

interface BaselineRow {
    id: string;
    organization_id: string;
    organization_name: string;
    methodology_version: string;
    baseline_annual_cost_cents: number;
    status: string;
    created_at: string;
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/baselines/page.tsx —
 * every organization's baselines, no filters/pagination, matching v2.
 * Locking is `admin.role:ADMIN,VERIFICATION_ANALYST`.
 */
export default function Baselines({ baselines }: { baselines: BaselineRow[] }) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const role = auth.admin?.role;
    const canLock = (['ADMIN', 'VERIFICATION_ANALYST'] as AdminRole[]).includes(
        role as AdminRole,
    );
    const pending = baselines.filter((b) => b.status === 'DRAFT').length;

    return (
        <div>
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Baselines
                </h1>
                <p className="text-text-secondary text-sm">
                    {pending} pending · {baselines.length} total
                </p>
            </div>

            {baselines.length === 0 ? (
                <div className="border-border bg-background rounded-card text-text-secondary border p-8 text-center text-sm">
                    No baselines yet.
                </div>
            ) : (
                <div className="border-border bg-background rounded-card overflow-x-auto border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="px-4 py-3">Organization</th>
                                <th className="px-4 py-3">Methodology</th>
                                <th className="px-4 py-3">
                                    Baseline annual cost
                                </th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Created</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {baselines.map((b) => (
                                <tr
                                    key={b.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`${base}/organizations/${b.organization_id}`}
                                            className="text-accent font-medium hover:underline"
                                        >
                                            {b.organization_name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        {b.methodology_version}
                                        {b.methodology_version.startsWith(
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
                                        {formatEurCents(
                                            b.baseline_annual_cost_cents,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                b.status === 'LOCKED'
                                                    ? 'cheap'
                                                    : b.status === 'SUPERSEDED'
                                                      ? 'neutral'
                                                      : 'mid'
                                            }
                                            showGlyph={false}
                                        >
                                            {b.status.toLowerCase()}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            b.created_at,
                                        ).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3">
                                        {b.status === 'DRAFT' && canLock && (
                                            <Link
                                                href={`${base}/baselines/${b.id}/lock`}
                                                method="post"
                                                as="button"
                                                className="bg-success rounded-control px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                            >
                                                Lock
                                            </Link>
                                        )}
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

Baselines.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
