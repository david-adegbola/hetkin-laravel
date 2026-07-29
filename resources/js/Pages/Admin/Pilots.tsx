import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { useAdminBasePath } from '@/lib/subdomain';
import { Head, Link } from '@inertiajs/react';
import { ReactElement } from 'react';

interface PilotApplicationRow {
    id: string;
    company_name: string;
    process_type: string;
    process_type_label: string;
    estimated_annual_spend_eur: number;
    win_condition: string | null;
    contact_name: string;
    contact_email: string;
    icp_score: number | null;
    status: string;
    created_at: string;
    reviewed_at: string | null;
    invite_url: string | null;
}

function ScoreBadge({ score }: { score: number | null }) {
    if (score === null) {
        return (
            <Badge variant="neutral" showGlyph={false}>
                —
            </Badge>
        );
    }
    const variant = score >= 70 ? 'cheap' : score >= 40 ? 'mid' : 'expensive';
    return (
        <Badge variant={variant} showGlyph={false}>
            {score}
        </Badge>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variant =
        status === 'APPROVED'
            ? 'cheap'
            : status === 'REJECTED'
              ? 'expensive'
              : status === 'NURTURE'
                ? 'mid'
                : 'neutral';
    return (
        <Badge variant={variant} showGlyph={false}>
            {status.replace('_', ' ')}
        </Badge>
    );
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/pilots/page.tsx —
 * sorted by ICP score server-side (App\Http\Controllers\Admin\
 * PilotApplicationController) so the reviewer triages by fit, not
 * submission order.
 */
export default function Pilots({
    pending,
    decided,
}: {
    pending: PilotApplicationRow[];
    decided: PilotApplicationRow[];
}) {
    const base = useAdminBasePath();

    return (
        <div>
            <Head title="Pilot Applications" />
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Pilot Applications
                </h1>
                <p className="text-text-secondary text-sm">
                    {pending.length} pending · {pending.length + decided.length}{' '}
                    total
                </p>
            </div>

            {pending.length === 0 ? (
                <div className="border-border bg-background rounded-card text-text-secondary mb-8 border p-8 text-center text-sm">
                    No pending applications.
                </div>
            ) : (
                <div className="border-border bg-background rounded-card mb-8 overflow-x-auto border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="px-4 py-3">Company</th>
                                <th className="px-4 py-3">Process type</th>
                                <th className="px-4 py-3">Est. spend</th>
                                <th className="px-4 py-3">ICP score</th>
                                <th className="px-4 py-3">Contact</th>
                                <th className="px-4 py-3">Submitted</th>
                                <th className="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {pending.map((app) => (
                                <tr
                                    key={app.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="px-4 py-3">
                                        <strong className="text-primary">
                                            {app.company_name}
                                        </strong>
                                        {app.win_condition && (
                                            <div className="text-text-secondary mt-1 text-xs">
                                                &ldquo;{app.win_condition}
                                                &rdquo;
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {app.process_type_label}
                                    </td>
                                    <td className="px-4 py-3">
                                        €
                                        {app.estimated_annual_spend_eur.toLocaleString()}
                                    </td>
                                    <td className="px-4 py-3">
                                        <ScoreBadge score={app.icp_score} />
                                    </td>
                                    <td className="px-4 py-3">
                                        {app.contact_name}
                                        <div className="text-text-secondary text-xs">
                                            {app.contact_email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {new Date(
                                            app.created_at,
                                        ).toLocaleDateString('en-GB')}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <Link
                                                href={`${base}/pilots/${app.id}/approve`}
                                                method="post"
                                                as="button"
                                                className="bg-success rounded-control px-3 py-1.5 text-xs font-semibold text-white hover:opacity-90"
                                            >
                                                Approve
                                            </Link>
                                            <Link
                                                href={`${base}/pilots/${app.id}/reject`}
                                                method="post"
                                                as="button"
                                                className="border-border text-text-secondary hover:border-error hover:text-error rounded-control border px-3 py-1.5 text-xs font-semibold"
                                            >
                                                Reject
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {decided.length > 0 && (
                <>
                    <h2 className="font-display text-primary mb-3 text-sm font-semibold">
                        Reviewed
                    </h2>
                    <div className="border-border bg-background rounded-card overflow-x-auto border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="px-4 py-3">Company</th>
                                    <th className="px-4 py-3">Process type</th>
                                    <th className="px-4 py-3">ICP score</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Reviewed</th>
                                    <th className="px-4 py-3">Invite link</th>
                                </tr>
                            </thead>
                            <tbody>
                                {decided.map((app) => (
                                    <tr
                                        key={app.id}
                                        className="border-border border-b last:border-0"
                                    >
                                        <td className="px-4 py-3">
                                            {app.company_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            {app.process_type_label}
                                        </td>
                                        <td className="px-4 py-3">
                                            <ScoreBadge score={app.icp_score} />
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge status={app.status} />
                                        </td>
                                        <td className="px-4 py-3">
                                            {app.reviewed_at
                                                ? new Date(
                                                      app.reviewed_at,
                                                  ).toLocaleDateString('en-GB')
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {app.invite_url ? (
                                                <input
                                                    readOnly
                                                    value={app.invite_url}
                                                    className="border-border bg-background text-text-secondary rounded-control w-56 border px-2 py-1 text-xs"
                                                    aria-label={`Invite link for ${app.company_name}`}
                                                />
                                            ) : (
                                                <span className="text-text-secondary text-xs">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </>
            )}
        </div>
    );
}

Pilots.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
