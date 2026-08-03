import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FieldError, Input, Label, Select } from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { formatScheduleDateTime } from '@/lib/format';
import { usePortalBasePath } from '@/lib/subdomain';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

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

interface ScheduleData {
    version: number;
    status: string;
    hasEligibleBlocks: boolean;
    blocks: ScheduleBlockRow[];
}

function LabourClassificationBadge({ value }: { value: LabourClassification }) {
    return value === 'UNMANNED' ? (
        <Badge variant="navy" showGlyph={false}>
            Reschedulable
        </Badge>
    ) : (
        <Badge variant="mid" showGlyph={false}>
            Requires staffed production
        </Badge>
    );
}

interface OptimizationStatus {
    recommendationCount: number;
}

interface ApplyDecisionsStatus {
    newVersion: number;
    blocksChanged: number;
}

interface ImportStatus {
    rowsWritten: number;
    newVersion: number;
}

function AddBlockForm({ base }: { base: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        asset_label: '',
        start_at: '',
        end_at: '',
        estimated_kwh: '',
        // Pre-selected to the conservative value, not blank — matches
        // AddScheduleBlockAction's own default and the creating
        // migration's DB-level default. Marking a block as reschedulable is
        // a deliberate choice, never the silent/default outcome.
        labour_classification: 'LABOUR_BOUND' as LabourClassification,
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
            className="grid gap-3 sm:grid-cols-5 sm:items-end"
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
                <Label htmlFor="start_at">Start (Helsinki time)</Label>
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
                <Label htmlFor="end_at">End (Helsinki time)</Label>
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
            <div>
                <Label htmlFor="labour_classification">
                    Can this activity be rescheduled without changing staffed
                    production?
                </Label>
                <Select
                    id="labour_classification"
                    required
                    value={data.labour_classification}
                    onChange={(e) =>
                        setData(
                            'labour_classification',
                            e.target.value as LabourClassification,
                        )
                    }
                >
                    <option value="LABOUR_BOUND">
                        No — requires staffed production
                    </option>
                    <option value="UNMANNED">Yes — reschedulable</option>
                </Select>
                <FieldError>{errors.labour_classification}</FieldError>
            </div>
            <div className="sm:col-span-5">
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Adding…' : 'Add block'}
                </Button>
            </div>
        </form>
    );
}

function RunOptimizationButton({
    base,
    optimizationStatus,
}: {
    base: string;
    optimizationStatus?: OptimizationStatus;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit() {
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/schedule/optimize`,
            {},
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.optimization ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={submit}
                disabled={processing}
            >
                {processing ? 'Running…' : 'Run optimization'}
            </Button>
            <FieldError>{error}</FieldError>
            {optimizationStatus && (
                <p className="text-success mt-1.5 text-xs font-medium">
                    {optimizationStatus.recommendationCount > 0
                        ? `${optimizationStatus.recommendationCount} recommendation${optimizationStatus.recommendationCount === 1 ? '' : 's'} generated — see the dashboard.`
                        : 'No cheaper windows found for the current schedule.'}
                </p>
            )}
        </div>
    );
}

function ApplyDecisionsButton({
    base,
    applyDecisionsStatus,
}: {
    base: string;
    applyDecisionsStatus?: ApplyDecisionsStatus;
}) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit() {
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/schedule/apply-decisions`,
            {},
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.applyDecisions ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={submit}
                disabled={processing}
            >
                {processing ? 'Applying…' : 'Apply decisions to a new version'}
            </Button>
            <FieldError>{error}</FieldError>
            {applyDecisionsStatus && (
                <p className="text-success mt-1.5 text-xs font-medium">
                    Created version {applyDecisionsStatus.newVersion} —{' '}
                    {applyDecisionsStatus.blocksChanged} block(s) updated.
                </p>
            )}
        </div>
    );
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/schedule/page.tsx —
 * including RunOptimizationButton/ApplyDecisionsButton and the
 * pending-decision-count display, now that the Optimization/
 * Recommendations domain exists (Stage 6).
 */
export default function Schedule({
    schedule,
    pendingDecisionCount,
    optimizationStatus,
    applyDecisionsStatus,
    status,
}: {
    schedule: ScheduleData | null;
    pendingDecisionCount: number;
    optimizationStatus?: OptimizationStatus;
    applyDecisionsStatus?: ApplyDecisionsStatus;
    status?: ImportStatus | string;
}) {
    const base = usePortalBasePath();

    return (
        <>
            <Head title="Schedule" />
            <PortalPageHeader title="Schedule" />
            <Card className="mb-4">
                <h3 className="font-display text-primary mb-3 text-base font-semibold">
                    Add a schedule block
                </h3>
                <p className="text-text-secondary mb-4 text-xs">
                    Add a production run manually, or import an ERP-exported CSV
                    as a new schedule version. A block needs both an estimated
                    energy use and must be marked as reschedulable without
                    changing staffed production to be considered for timing
                    recommendations. Activities that require staffed production
                    are tracked here but are not automatically recommended.
                </p>
                <Link
                    href={`${base}/schedule/import`}
                    className="text-accent mb-4 inline-block text-sm font-medium hover:underline"
                >
                    Import ERP schedule CSV
                </Link>
                <AddBlockForm base={base} />
            </Card>

            {typeof status !== 'string' && status && (
                <div className="bg-success-subtle text-success mb-4 rounded-lg px-4 py-3 text-sm font-medium">
                    Imported {status.rowsWritten.toLocaleString()} schedule
                    block{status.rowsWritten === 1 ? '' : 's'} as version{' '}
                    {status.newVersion}.
                </div>
            )}

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
                        <div className="flex flex-wrap items-center gap-3">
                            <RunOptimizationButton
                                base={base}
                                optimizationStatus={optimizationStatus}
                            />
                            {pendingDecisionCount > 0 && (
                                <ApplyDecisionsButton
                                    base={base}
                                    applyDecisionsStatus={applyDecisionsStatus}
                                />
                            )}
                            {!schedule.hasEligibleBlocks && (
                                <p className="text-text-secondary mt-1.5 text-xs">
                                    Add estimated energy use to at least one
                                    reschedulable activity to run optimization.
                                </p>
                            )}
                        </div>
                    </div>
                    {pendingDecisionCount > 0 && (
                        <p className="text-text-secondary mb-3 text-xs">
                            {pendingDecisionCount} accepted/partially-adopted
                            recommendation
                            {pendingDecisionCount === 1 ? '' : 's'} not yet
                            applied to the schedule.
                        </p>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="py-2 pr-4">Site</th>
                                    <th className="py-2 pr-4">Asset</th>
                                    <th className="py-2 pr-4">Start</th>
                                    <th className="py-2 pr-4">End</th>
                                    <th className="py-2 pr-4">Est. kWh</th>
                                    <th className="py-2 pr-4">Labour</th>
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
                                            {formatScheduleDateTime(b.start_at)}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {formatScheduleDateTime(b.end_at)}
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
                                                value={b.labour_classification}
                                            />
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
