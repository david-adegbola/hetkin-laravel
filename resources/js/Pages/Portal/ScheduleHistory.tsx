import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head } from '@inertiajs/react';
import { ReactElement } from 'react';

type LabourClassification = 'UNMANNED' | 'LABOUR_BOUND';

interface ScheduleBlockRow {
    id: string;
    site_name: string;
    asset_label: string;
    start_at: string;
    end_at: string;
    estimated_kwh: number | null;
    labour_classification: LabourClassification;
}

function LabourClassificationBadge({ value }: { value: LabourClassification }) {
    return value === 'UNMANNED' ? (
        <Badge variant="navy" showGlyph={false}>
            Unmanned
        </Badge>
    ) : (
        <Badge variant="mid" showGlyph={false}>
            Labour Bound
        </Badge>
    );
}

interface ScheduleVersion {
    id: string;
    version: number;
    status: string;
    created_at: string;
    submitted_by: string | null;
    blocks: ScheduleBlockRow[];
}

/**
 * Read-only reconstruction view over every ProductionSchedule version —
 * mirrors the Next.js app's src/app/(portal)/dashboard/schedule/history/
 * page.tsx exactly. Nothing here is editable; this page exists so a
 * dispute or audit doesn't depend on anyone's memory of what changed and
 * when.
 */
export default function ScheduleHistory({
    versions,
}: {
    versions: ScheduleVersion[];
}) {
    return (
        <>
            <Head title="Schedule history" />
            <PortalPageHeader
                title="Schedule history"
                meta="Every version is kept — none are edited or deleted in place."
            />
            {versions.length === 0 ? (
                <Card className="text-text-secondary py-12 text-center text-sm">
                    No schedule versions yet.
                </Card>
            ) : (
                <div className="flex flex-col gap-3">
                    {versions.map((version) => (
                        <Card key={version.id}>
                            <details>
                                <summary className="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-2.5">
                                        <span className="font-display text-primary text-base font-semibold">
                                            Version {version.version}
                                        </span>
                                        <Badge
                                            variant={
                                                version.status === 'ACTIVE'
                                                    ? 'cheap'
                                                    : version.status ===
                                                        'SUPERSEDED'
                                                      ? 'mid'
                                                      : 'neutral'
                                            }
                                            showGlyph={false}
                                        >
                                            {version.status.toLowerCase()}
                                        </Badge>
                                    </div>
                                    <div className="text-text-secondary text-xs">
                                        {version.blocks.length} block
                                        {version.blocks.length === 1
                                            ? ''
                                            : 's'}{' '}
                                        ·{' '}
                                        {new Date(
                                            version.created_at,
                                        ).toLocaleString('en-GB')}
                                        {version.submitted_by
                                            ? ` · ${version.submitted_by}`
                                            : ''}
                                    </div>
                                </summary>
                                <div className="border-border mt-4 overflow-x-auto border-t pt-4">
                                    <table className="w-full text-left text-sm">
                                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                            <tr>
                                                <th className="py-2 pr-4">
                                                    Site
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Asset
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Start
                                                </th>
                                                <th className="py-2 pr-4">
                                                    End
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Est. kWh
                                                </th>
                                                <th className="py-2 pr-4">
                                                    Labour
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {version.blocks.map((b) => (
                                                <tr
                                                    key={b.id}
                                                    className="border-border border-b last:border-0"
                                                >
                                                    <td className="py-2 pr-4">
                                                        {b.site_name}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {b.asset_label}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {new Date(
                                                            b.start_at,
                                                        ).toLocaleString(
                                                            'en-GB',
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {new Date(
                                                            b.end_at,
                                                        ).toLocaleString(
                                                            'en-GB',
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        {b.estimated_kwh
                                                            ? b.estimated_kwh.toLocaleString(
                                                                  'en-GB',
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td className="py-2 pr-4">
                                                        <LabourClassificationBadge
                                                            value={
                                                                b.labour_classification
                                                            }
                                                        />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </Card>
                    ))}
                </div>
            )}
        </>
    );
}

ScheduleHistory.layout = (page: ReactElement) => (
    <PortalLayout>{page}</PortalLayout>
);
