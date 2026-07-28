import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactElement } from 'react';

/**
 * Portal overview — see App\Http\Controllers\Portal\DashboardController's
 * docblock for why this is an honest interim page rather than an
 * approximation of v2's full stat-card dashboard (src/app/(portal)/
 * dashboard/page.tsx): that page's numbers come from domains (Verification,
 * Recommendations, Alerts) that don't exist in this port yet. Links to
 * what Stage 5 actually built (Schedule, Data) instead of showing zeros
 * that would look identical whether nothing happened yet or something is
 * broken.
 */
export default function Dashboard() {
    const { organization } = usePage<PageProps>().props;
    const base = usePortalBasePath();

    return (
        <>
            <PortalPageHeader
                title="Dashboard"
                meta={
                    organization
                        ? `${organization.name}${organization.status === 'PILOT' ? ' · Pilot' : ''}`
                        : undefined
                }
            />
            <Card>
                <h2 className="font-display text-primary mb-2 text-base font-semibold">
                    Get started
                </h2>
                <p className="text-text-secondary mb-4 text-sm">
                    Upload your electricity consumption data and add your
                    production schedule to start building toward a savings
                    assessment. Verified savings, recommendations, and alerts
                    will appear here once that's in place.
                </p>
                <div className="flex flex-wrap gap-3">
                    <Link
                        href={`${base}/data`}
                        className="text-accent hover:text-accent-hover text-sm font-medium"
                    >
                        Upload data →
                    </Link>
                    <Link
                        href={`${base}/schedule`}
                        className="text-accent hover:text-accent-hover text-sm font-medium"
                    >
                        Add schedule →
                    </Link>
                </div>
            </Card>
        </>
    );
}

Dashboard.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
