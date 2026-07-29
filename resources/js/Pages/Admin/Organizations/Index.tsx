import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { useAdminBasePath } from '@/lib/subdomain';
import { Head, Link } from '@inertiajs/react';
import { ReactElement } from 'react';

interface OrganizationRow {
    id: string;
    name: string;
    slug: string;
    status: string;
    members_count: number;
    has_locked_baseline: boolean;
    created_at: string;
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'ACTIVE'
            ? 'cheap'
            : status === 'CHURNED'
              ? 'expensive'
              : status === 'PAUSED'
                ? 'mid'
                : 'neutral';
    return (
        <Badge variant={variant} showGlyph={false}>
            {status.toLowerCase()}
        </Badge>
    );
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/organizations/
 * page.tsx — deliberately just a scannable index into the org-360 detail
 * page, no filters/pagination/search, matching v2 exactly.
 */
export default function OrganizationsIndex({
    organizations,
}: {
    organizations: OrganizationRow[];
}) {
    const base = useAdminBasePath();

    return (
        <div>
            <Head title="Organizations" />
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Organizations
                </h1>
                <p className="text-text-secondary text-sm">
                    {organizations.length} total
                </p>
            </div>

            {organizations.length === 0 ? (
                <div className="border-border bg-background rounded-card text-text-secondary border p-8 text-center text-sm">
                    No organizations yet.
                </div>
            ) : (
                <div className="border-border bg-background rounded-card overflow-x-auto border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Members</th>
                                <th className="px-4 py-3">Baseline</th>
                                <th className="px-4 py-3">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            {organizations.map((org) => (
                                <tr
                                    key={org.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`${base}/organizations/${org.id}`}
                                            className="text-accent font-semibold hover:underline"
                                        >
                                            {org.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={org.status} />
                                    </td>
                                    <td className="px-4 py-3">
                                        {org.members_count}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                org.has_locked_baseline
                                                    ? 'cheap'
                                                    : 'neutral'
                                            }
                                            showGlyph={false}
                                        >
                                            {org.has_locked_baseline
                                                ? 'locked'
                                                : 'none'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            org.created_at,
                                        ).toLocaleDateString('en-GB')}
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

OrganizationsIndex.layout = (page: ReactElement) => (
    <AdminLayout>{page}</AdminLayout>
);
