import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardTitle } from '@/Components/ui/Card';
import { FieldError, Input, Label } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { useAdminBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

interface JobRun {
    id: string;
    job_name: string;
    status: string;
    triggered_by: string;
    started_at: string;
    completed_at: string | null;
    summary: Record<string, unknown> | null;
    error_message: string | null;
}

interface KnownJob {
    job_name: string;
    triggerable: boolean;
    last_run: JobRun | null;
}

const JOB_LABELS: Record<string, { label: string; description: string }> = {
    'ingest-prices': {
        label: 'Ingest ENTSO-E prices',
        description:
            "Fetches today + tomorrow's day-ahead Finnish prices. Meant to run 1-2×/day.",
    },
    'run-verification': {
        label: 'Run monthly verification',
        description:
            'Computes VerificationRecords for the previous calendar month. Meant to run monthly.',
    },
    'generate-alerts': {
        label: 'Generate data-gap alerts',
        description:
            'Flags orgs whose meter data has gone stale or a metering point is unhealthy. Meant to run daily.',
    },
};

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'SUCCEEDED'
            ? 'cheap'
            : status === 'FAILED'
              ? 'expensive'
              : 'mid';
    return (
        <Badge variant={variant} showGlyph={false}>
            {status.toLowerCase()}
        </Badge>
    );
}

function formatDuration(run: JobRun): string {
    if (!run.completed_at) return 'running…';
    const ms =
        new Date(run.completed_at).getTime() -
        new Date(run.started_at).getTime();
    return ms < 1000 ? `${ms}ms` : `${(ms / 1000).toFixed(1)}s`;
}

function formatTriggeredBy(triggeredBy: string): string {
    return triggeredBy === 'cron' ? 'cron' : `admin:${triggeredBy.slice(0, 8)}`;
}

function JobCard({ job, base }: { job: KnownJob; base: string }) {
    const meta = JOB_LABELS[job.job_name] ?? {
        label: job.job_name,
        description: '',
    };
    const [processing, setProcessing] = useState(false);

    function runNow() {
        setProcessing(true);
        router.post(
            `${base}/jobs/${job.job_name}`,
            {},
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    }

    return (
        <Card>
            <div className="mb-2 flex items-center justify-between">
                <CardTitle>{meta.label}</CardTitle>
                {job.last_run ? (
                    <StatusBadge status={job.last_run.status} />
                ) : (
                    <Badge variant="neutral" showGlyph={false}>
                        never run
                    </Badge>
                )}
            </div>
            <p className="text-text-secondary mb-3 text-sm">
                {meta.description}
            </p>
            {job.last_run && (
                <p className="text-text-secondary mb-3 text-xs">
                    Last run:{' '}
                    {new Date(job.last_run.started_at).toLocaleString('en-GB')}{' '}
                    · {formatDuration(job.last_run)}
                </p>
            )}
            {job.triggerable ? (
                <Button
                    type="button"
                    size="sm"
                    onClick={runNow}
                    disabled={processing}
                >
                    {processing ? 'Running…' : 'Run now'}
                </Button>
            ) : (
                <p className="text-text-secondary text-xs">
                    Not wired up in this port yet.
                </p>
            )}
        </Card>
    );
}

/**
 * Ports the Next.js app's BackfillPricesForm.tsx exactly — fills
 * ElectricityPrice for a past date range on demand, since ingest-prices
 * only ever covers "today + tomorrow" going forward. Safe to re-run:
 * already-covered days are skipped, and writes are idempotent. Capped at
 * 400 days here (enforced server-side by App\Http\Requests\Admin\
 * BackfillPricesRequest); a longer range needs `php artisan
 * prices:backfill` run locally instead.
 */
function BackfillPricesForm({ base }: { base: string }) {
    const { flash } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors } = useForm({
        start_date: '',
        end_date: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/jobs/backfill-prices`, { preserveScroll: true });
    };

    return (
        <Card className="mb-6">
            <CardTitle className="mb-1">Backfill historical prices</CardTitle>
            <p className="text-text-secondary mb-3 text-xs">
                Fetches ENTSO-E day-ahead FI prices for a past date range. Safe
                to re-run — already-covered days are skipped, and writes are
                idempotent. Capped at 400 days here; for a longer range run{' '}
                <code className="bg-background rounded px-1 py-0.5">
                    php artisan prices:backfill
                </code>{' '}
                locally instead.
            </p>
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3">
                <div>
                    <Label htmlFor="start_date">Start date</Label>
                    <Input
                        id="start_date"
                        type="date"
                        value={data.start_date}
                        onChange={(e) => setData('start_date', e.target.value)}
                        required
                    />
                    <FieldError>{errors.start_date}</FieldError>
                </div>
                <div>
                    <Label htmlFor="end_date">End date (exclusive)</Label>
                    <Input
                        id="end_date"
                        type="date"
                        value={data.end_date}
                        onChange={(e) => setData('end_date', e.target.value)}
                        required
                    />
                    <FieldError>{errors.end_date}</FieldError>
                </div>
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Backfilling…' : 'Run backfill'}
                </Button>
            </form>
            {flash.backfill_result && (
                <p
                    className={
                        flash.backfill_result.status === 'success'
                            ? 'bg-success-subtle text-success mt-3 rounded-lg px-4 py-3 text-sm font-medium'
                            : 'bg-error-subtle text-error mt-3 rounded-lg px-4 py-3 text-sm font-medium'
                    }
                >
                    {flash.backfill_result.message}
                </p>
            )}
        </Card>
    );
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/jobs/page.tsx. All
 * three known jobs plus the backfill-prices form are wired to real
 * triggers, `admin.role:ADMIN` only.
 */
export default function Jobs({
    known_jobs,
    recent_runs,
}: {
    known_jobs: KnownJob[];
    recent_runs: JobRun[];
}) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const isAdmin = auth.admin?.role === 'ADMIN';

    return (
        <div>
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Jobs
                </h1>
                <p className="text-text-secondary text-sm">
                    Background job status and manual triggers.
                </p>
            </div>

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                {known_jobs.map((job) => (
                    <JobCard
                        key={job.job_name}
                        job={{
                            ...job,
                            triggerable: job.triggerable && isAdmin,
                        }}
                        base={base}
                    />
                ))}
            </div>

            {isAdmin && <BackfillPricesForm base={base} />}

            <Card>
                <CardTitle className="mb-3">Recent runs</CardTitle>
                {recent_runs.length === 0 ? (
                    <p className="text-text-secondary text-sm">No runs yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="py-2 pr-4">Job</th>
                                    <th className="py-2 pr-4">Started</th>
                                    <th className="py-2 pr-4">Duration</th>
                                    <th className="py-2 pr-4">Triggered by</th>
                                    <th className="py-2 pr-4">Status</th>
                                    <th className="py-2 pr-4">Detail</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent_runs.map((run) => (
                                    <tr
                                        key={run.id}
                                        className="border-border border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4 font-mono text-xs">
                                            {run.job_name}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {new Date(
                                                run.started_at,
                                            ).toLocaleString('en-GB')}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {formatDuration(run)}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {formatTriggeredBy(
                                                run.triggered_by,
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            <StatusBadge status={run.status} />
                                        </td>
                                        <td className="max-w-xs truncate py-2 pr-4 text-xs">
                                            {run.status === 'FAILED'
                                                ? run.error_message
                                                : run.summary
                                                  ? JSON.stringify(run.summary)
                                                  : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>
        </div>
    );
}

Jobs.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
