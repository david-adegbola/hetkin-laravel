import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { buttonVariants } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Link } from '@inertiajs/react';
import { ReactElement } from 'react';

interface MeteringPointRow {
    id: string;
    label: string;
    site_name: string;
    data_source: 'DATAHUB' | 'MANUAL_UPLOAD';
    connection_healthy: boolean;
    last_reading_at: string | null;
}

/** Mirrors the Next.js app's src/app/(portal)/dashboard/data/page.tsx. */
export default function Data({
    meteringPoints,
}: {
    meteringPoints: MeteringPointRow[];
}) {
    const base = usePortalBasePath();

    return (
        <>
            <PortalPageHeader title="Data" />
            <div className="mb-4">
                <Link
                    href={`${base}/data/upload`}
                    className={buttonVariants({ size: 'sm' })}
                >
                    Upload meter data
                </Link>
            </div>
            {meteringPoints.length === 0 ? (
                <Card className="text-text-secondary py-12 text-center text-sm">
                    No metering points connected yet. Connect via Fingrid
                    Datahub consent, or upload meter data manually as a
                    fallback.
                </Card>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2">
                    {meteringPoints.map((mp) => (
                        <Card key={mp.id}>
                            <div className="mb-2 flex items-center justify-between">
                                <h3 className="font-display text-primary text-sm font-semibold">
                                    {mp.label}
                                </h3>
                                <Badge
                                    variant={
                                        mp.connection_healthy
                                            ? 'cheap'
                                            : 'expensive'
                                    }
                                    showGlyph={false}
                                >
                                    {mp.connection_healthy
                                        ? 'Healthy'
                                        : 'Attention needed'}
                                </Badge>
                            </div>
                            <p className="text-text-secondary text-xs">
                                {mp.site_name}
                            </p>
                            <p className="text-text-secondary mt-1 text-xs">
                                Source:{' '}
                                {mp.data_source === 'DATAHUB'
                                    ? 'Fingrid Datahub'
                                    : 'Manual upload'}
                            </p>
                            <p className="text-text-secondary mt-1 text-xs">
                                Last reading:{' '}
                                {mp.last_reading_at
                                    ? new Date(
                                          mp.last_reading_at,
                                      ).toLocaleString('en-GB')
                                    : 'None yet'}
                            </p>
                        </Card>
                    ))}
                </div>
            )}
        </>
    );
}

Data.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
