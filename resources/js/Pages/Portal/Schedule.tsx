import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Link, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactElement } from 'react';

interface ScheduleBlockRow {
    id: string;
    site_name: string;
    asset_label: string;
    start_at: string;
    end_at: string;
    estimated_kwh: number | null;
}

interface ScheduleData {
    version: number;
    status: string;
    blocks: ScheduleBlockRow[];
}

function AddBlockForm({ base }: { base: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        asset_label: '',
        start_at: '',
        end_at: '',
        estimated_kwh: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/schedule`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 sm:grid-cols-4 sm:items-end"
        >
            <div className="sm:col-span-1">
                <Label htmlFor="asset_label">Asset / line</Label>
                <Input
                    id="asset_label"
                    placeholder="e.g. Extruder 2"
                    required
                    value={data.asset_label}
                    onChange={(e) => setData('asset_label', e.target.value)}
                />
                <FieldError>{errors.asset_label}</FieldError>
            </div>
            <div>
                <Label htmlFor="start_at">Start</Label>
                <Input
                    id="start_at"
                    type="datetime-local"
                    required
                    value={data.start_at}
                    onChange={(e) => setData('start_at', e.target.value)}
                />
                <FieldError>{errors.start_at}</FieldError>
            </div>
            <div>
                <Label htmlFor="end_at">End</Label>
                <Input
                    id="end_at"
                    type="datetime-local"
                    required
                    value={data.end_at}
                    onChange={(e) => setData('end_at', e.target.value)}
                />
                <FieldError>{errors.end_at}</FieldError>
            </div>
            <div>
                <Label htmlFor="estimated_kwh">Est. energy use (kWh)</Label>
                <Input
                    id="estimated_kwh"
                    type="number"
                    min="0"
                    step="0.1"
                    placeholder="Optional"
                    value={data.estimated_kwh}
                    onChange={(e) => setData('estimated_kwh', e.target.value)}
                />
                <FieldError>{errors.estimated_kwh}</FieldError>
            </div>
            <div className="sm:col-span-4">
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Adding…' : 'Add block'}
                </Button>
            </div>
        </form>
    );
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/schedule/page.tsx —
 * minus RunOptimizationButton/ApplyDecisionsButton and the
 * pending-decision-count display, which depend on the Optimization/
 * Recommendations domain (Stage 6, not built yet). See
 * App\Http\Controllers\Portal\ScheduleController's docblock.
 */
export default function Schedule({
    schedule,
}: {
    schedule: ScheduleData | null;
}) {
    const base = usePortalBasePath();

    return (
        <>
            <PortalPageHeader title="Schedule" />
            <Card className="mb-4">
                <h3 className="font-display text-primary mb-3 text-base font-semibold">
                    Add a schedule block
                </h3>
                <p className="text-text-secondary mb-4 text-xs">
                    Add each production run manually for now — a CSV/ERP import
                    path is planned once there is a real export format to build
                    against. Estimated energy use is optional but required for
                    that block to be considered when running optimization.
                </p>
                <AddBlockForm base={base} />
            </Card>

            {!schedule || schedule.blocks.length === 0 ? (
                <Card className="text-text-secondary py-12 text-center text-sm">
                    No production schedule submitted yet. Add a block above to
                    get started.
                </Card>
            ) : (
                <Card>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div className="text-text-secondary text-sm">
                            Version {schedule.version} ·{' '}
                            {schedule.status.toLowerCase()}{' '}
                            <Link
                                href={`${base}/schedule/history`}
                                className="text-accent ml-1 font-medium hover:underline"
                            >
                                View history
                            </Link>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="py-2 pr-4">Site</th>
                                    <th className="py-2 pr-4">Asset</th>
                                    <th className="py-2 pr-4">Start</th>
                                    <th className="py-2 pr-4">End</th>
                                    <th className="py-2 pr-4">Est. kWh</th>
                                </tr>
                            </thead>
                            <tbody>
                                {schedule.blocks.map((b) => (
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
                                            ).toLocaleString('en-GB')}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {new Date(b.end_at).toLocaleString(
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
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}
        </>
    );
}

Schedule.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
