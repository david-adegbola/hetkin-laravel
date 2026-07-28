import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardTitle } from '@/Components/ui/Card';
import {
    FieldError,
    Input,
    Label,
    Select,
    Textarea,
} from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatEurCents } from '@/lib/format';
import { useAdminBasePath } from '@/lib/subdomain';
import type { AdminRole, PageProps } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

interface OrganizationDetail {
    id: string;
    name: string;
    slug: string;
    status: string;
    process_type: string | null;
    base_fee_cents: number | null;
    revenue_share_percent: number | null;
    created_at: string;
    pilot_application_id: string | null;
}

interface MemberRow {
    id: string;
    user_id: string;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

interface InvitationRow {
    id: string;
    email: string;
    role: string;
    status: string;
    expires_at: string;
    created_at: string;
}

interface NoteRow {
    id: string;
    category: string;
    body: string;
    pinned: boolean;
    author_name: string;
    created_at: string;
}

interface BaselineRow {
    id: string;
    status: string;
    methodology_version: string;
    baseline_annual_cost_cents: number;
    created_at: string;
}

interface VerificationRow {
    id: string;
    period_start: string;
    period_end: string;
    review_status: string;
    verified_savings_cents: number;
    variance_flagged: boolean;
}

interface InvoiceRow {
    id: string;
    status: string;
    total_cents: number;
    issued_at: string | null;
    created_at: string;
}

interface ActivityRow {
    id: string;
    action: string;
    actor_type: string;
    actor_id: string;
    entity_type: string;
    entity_id: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

interface AlertRow {
    id: string;
    type: string;
    severity: string;
    title: string;
    body: string;
    created_at: string;
}

interface ShowProps {
    organization: OrganizationDetail;
    members: MemberRow[];
    invitations: InvitationRow[];
    notes: NoteRow[];
    baselines: BaselineRow[];
    verification_records: VerificationRow[];
    invoices: InvoiceRow[];
    activity: ActivityRow[];
    alerts: AlertRow[];
}

function hasRole(role: AdminRole | undefined, allowed: AdminRole[]): boolean {
    return !!role && allowed.includes(role);
}

function severityDotClass(severity: string): string {
    if (severity === 'WARNING') return 'bg-error';
    if (severity === 'OPPORTUNITY') return 'bg-success';

    return 'bg-primary';
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/organizations/
 * [id]/page.tsx — the org 360 view, including the "Recent alerts" section
 * (Stage 10). Every mutation form is conditionally rendered by role
 * client-side, matching v2's own UX (the server still enforces every role
 * gate regardless — see routes/admin-protected.php).
 */
export default function Show({
    organization,
    members,
    invitations,
    notes,
    baselines,
    verification_records,
    invoices,
    activity,
    alerts,
}: ShowProps) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const role = auth.admin?.role;
    const isAdmin = hasRole(role, ['ADMIN']);
    const canManageMembers = hasRole(role, ['ADMIN', 'SUPPORT']);

    return (
        <div>
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="font-display text-primary text-xl font-semibold">
                        {organization.name}
                    </h1>
                    <p className="text-text-secondary text-sm">
                        {organization.slug} · created{' '}
                        {new Date(organization.created_at).toLocaleDateString(
                            'en-GB',
                        )}
                    </p>
                </div>
                <Link
                    href={`${base}/organizations`}
                    className="text-accent text-sm font-medium hover:underline"
                >
                    ← All organizations
                </Link>
            </div>

            <div className="mb-6 grid gap-4 lg:grid-cols-2">
                <StatusCard organization={organization} canEdit={isAdmin} />
                <PricingCard organization={organization} canEdit={isAdmin} />
            </div>

            <Card className="mb-6">
                <CardTitle className="mb-3">
                    Members ({members.length})
                </CardTitle>
                <MembersTable
                    members={members}
                    organizationId={organization.id}
                    base={base}
                    canManage={canManageMembers}
                />
            </Card>

            <Card className="mb-6">
                <CardTitle className="mb-3">
                    Pending invitations (
                    {invitations.filter((i) => i.status === 'pending').length})
                </CardTitle>
                <InvitationsTable
                    invitations={invitations}
                    organizationId={organization.id}
                    base={base}
                    canManage={canManageMembers}
                />
            </Card>

            <Card className="mb-6">
                <CardTitle className="mb-3">Internal notes</CardTitle>
                <NotesSection
                    notes={notes}
                    organizationId={organization.id}
                    base={base}
                />
            </Card>

            <div className="mb-6 grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardTitle className="mb-3">Baselines</CardTitle>
                    {baselines.length === 0 ? (
                        <EmptyRow />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {baselines.map((b) => (
                                <li
                                    key={b.id}
                                    className="border-border border-b pb-2 last:border-0"
                                >
                                    <div className="flex items-center justify-between">
                                        <span>{b.methodology_version}</span>
                                        <Badge
                                            variant={
                                                b.status === 'LOCKED'
                                                    ? 'cheap'
                                                    : 'neutral'
                                            }
                                            showGlyph={false}
                                        >
                                            {b.status.toLowerCase()}
                                        </Badge>
                                    </div>
                                    <div className="text-text-secondary text-xs">
                                        {formatEurCents(
                                            b.baseline_annual_cost_cents,
                                        )}
                                        /yr
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardTitle className="mb-3">Verification</CardTitle>
                    {verification_records.length === 0 ? (
                        <EmptyRow />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {verification_records.map((v) => (
                                <li
                                    key={v.id}
                                    className="border-border border-b pb-2 last:border-0"
                                >
                                    <div className="flex items-center justify-between">
                                        <span>
                                            {new Date(
                                                v.period_start,
                                            ).toLocaleDateString('en-GB')}
                                        </span>
                                        <Badge
                                            variant={
                                                v.review_status ===
                                                'LIVE_APPROVED'
                                                    ? 'cheap'
                                                    : v.review_status ===
                                                        'DISPUTED'
                                                      ? 'expensive'
                                                      : 'neutral'
                                            }
                                            showGlyph={false}
                                        >
                                            {v.review_status
                                                .toLowerCase()
                                                .replace(/_/g, ' ')}
                                        </Badge>
                                    </div>
                                    <div className="text-text-secondary text-xs">
                                        {formatEurCents(
                                            v.verified_savings_cents,
                                        )}{' '}
                                        savings
                                        {v.variance_flagged && (
                                            <span className="text-error">
                                                {' '}
                                                · flagged
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>

                <Card>
                    <CardTitle className="mb-3">Invoices</CardTitle>
                    {invoices.length === 0 ? (
                        <EmptyRow />
                    ) : (
                        <ul className="space-y-2 text-sm">
                            {invoices.map((inv) => (
                                <li
                                    key={inv.id}
                                    className="border-border border-b pb-2 last:border-0"
                                >
                                    <div className="flex items-center justify-between">
                                        <span>
                                            {formatEurCents(inv.total_cents)}
                                        </span>
                                        <Badge
                                            variant={
                                                inv.status === 'PAID'
                                                    ? 'cheap'
                                                    : inv.status === 'DISPUTED'
                                                      ? 'expensive'
                                                      : 'neutral'
                                            }
                                            showGlyph={false}
                                        >
                                            {inv.status.toLowerCase()}
                                        </Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>

            <Card className="mb-6">
                <CardTitle className="mb-3">Activity</CardTitle>
                {activity.length === 0 ? (
                    <EmptyRow />
                ) : (
                    <ul className="space-y-2 text-sm">
                        {activity.map((entry) => (
                            <li
                                key={entry.id}
                                className="border-border flex items-center justify-between border-b pb-2 last:border-0"
                            >
                                <span className="font-mono text-xs">
                                    {entry.action}
                                </span>
                                <span className="text-text-secondary text-xs">
                                    {new Date(entry.created_at).toLocaleString(
                                        'en-GB',
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card className="mb-6">
                <CardTitle className="mb-3">Recent alerts</CardTitle>
                {alerts.length === 0 ? (
                    <EmptyRow />
                ) : (
                    <ul className="space-y-3">
                        {alerts.map((alert) => (
                            <li
                                key={alert.id}
                                className="border-border flex items-start gap-3 border-b pb-3 last:border-0"
                            >
                                <span
                                    className={`mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ${severityDotClass(alert.severity)}`}
                                    aria-hidden="true"
                                />
                                <div className="flex-1">
                                    <div className="text-primary text-sm font-semibold">
                                        {alert.title}
                                    </div>
                                    <p className="text-text-secondary mt-0.5 text-sm">
                                        {alert.body}
                                    </p>
                                    <div className="text-text-secondary mt-1 text-xs">
                                        {new Date(
                                            alert.created_at,
                                        ).toLocaleDateString('en-GB')}
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            {isAdmin && <ErasureCard organization={organization} base={base} />}
        </div>
    );
}

function EmptyRow() {
    return <p className="text-text-secondary text-sm">None yet.</p>;
}

function StatusCard({
    organization,
    canEdit,
}: {
    organization: OrganizationDetail;
    canEdit: boolean;
}) {
    const base = useAdminBasePath();
    const [status, setStatus] = useState(organization.status);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/organizations/${organization.id}/status`,
            { status },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.status ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Card>
            <CardTitle className="mb-3">Status</CardTitle>
            {canEdit ? (
                <form onSubmit={submit} className="flex items-end gap-3">
                    <div className="flex-1">
                        <Label htmlFor="status">Status</Label>
                        <Select
                            id="status"
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                        >
                            <option value="PILOT">Pilot</option>
                            <option value="ACTIVE">Active</option>
                            <option value="PAUSED">Paused</option>
                            <option value="CHURNED">Churned</option>
                        </Select>
                        <FieldError>{error}</FieldError>
                    </div>
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Saving…' : 'Save'}
                    </Button>
                </form>
            ) : (
                <Badge variant="neutral" showGlyph={false}>
                    {organization.status.toLowerCase()}
                </Badge>
            )}
        </Card>
    );
}

function PricingCard({
    organization,
    canEdit,
}: {
    organization: OrganizationDetail;
    canEdit: boolean;
}) {
    const base = useAdminBasePath();
    const { data, setData, post, processing, errors } = useForm({
        base_fee_eur:
            organization.base_fee_cents !== null
                ? (organization.base_fee_cents / 100).toString()
                : '',
        revenue_share_percent:
            organization.revenue_share_percent !== null
                ? organization.revenue_share_percent.toString()
                : '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/organizations/${organization.id}/pricing`, {
            preserveScroll: true,
        });
    };

    if (!canEdit) {
        return (
            <Card>
                <CardTitle className="mb-3">Pricing</CardTitle>
                <p className="text-text-secondary text-sm">
                    {organization.base_fee_cents !== null
                        ? `${formatEurCents(organization.base_fee_cents)}/mo + ${organization.revenue_share_percent}% revenue share`
                        : 'Not set.'}
                </p>
            </Card>
        );
    }

    return (
        <Card>
            <CardTitle className="mb-3">Pricing</CardTitle>
            <form onSubmit={submit} className="flex items-end gap-3">
                <div className="flex-1">
                    <Label htmlFor="base_fee_eur">Base fee (EUR/mo)</Label>
                    <Input
                        id="base_fee_eur"
                        value={data.base_fee_eur}
                        onChange={(e) =>
                            setData('base_fee_eur', e.target.value)
                        }
                    />
                    <FieldError>{errors.base_fee_eur}</FieldError>
                </div>
                <div className="flex-1">
                    <Label htmlFor="revenue_share_percent">
                        Revenue share (%)
                    </Label>
                    <Input
                        id="revenue_share_percent"
                        value={data.revenue_share_percent}
                        onChange={(e) =>
                            setData('revenue_share_percent', e.target.value)
                        }
                    />
                </div>
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Saving…' : 'Save'}
                </Button>
            </form>
        </Card>
    );
}

function MembersTable({
    members,
    organizationId,
    base,
    canManage,
}: {
    members: MemberRow[];
    organizationId: string;
    base: string;
    canManage: boolean;
}) {
    if (members.length === 0) return <EmptyRow />;

    return (
        <table className="w-full text-left text-sm">
            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                <tr>
                    <th className="py-2 pr-4">Name</th>
                    <th className="py-2 pr-4">Email</th>
                    <th className="py-2 pr-4">Role</th>
                    {canManage && <th className="py-2 pr-4">Actions</th>}
                </tr>
            </thead>
            <tbody>
                {members.map((m) => (
                    <tr
                        key={m.id}
                        className="border-border border-b last:border-0"
                    >
                        <td className="py-2 pr-4">{m.name}</td>
                        <td className="py-2 pr-4">{m.email}</td>
                        <td className="py-2 pr-4">
                            {canManage ? (
                                <Select
                                    defaultValue={m.role}
                                    onChange={(e) =>
                                        router.post(
                                            `${base}/organizations/${organizationId}/members/${m.id}/role`,
                                            { role: e.target.value },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <option value="OWNER">Owner</option>
                                    <option value="SCHEDULER">Scheduler</option>
                                    <option value="FINANCE">Finance</option>
                                    <option value="VIEWER">Viewer</option>
                                </Select>
                            ) : (
                                m.role.toLowerCase()
                            )}
                        </td>
                        {canManage && (
                            <td className="py-2 pr-4">
                                <Link
                                    href={`${base}/organizations/${organizationId}/members/${m.id}`}
                                    method="delete"
                                    as="button"
                                    className="text-error text-xs font-medium hover:underline"
                                >
                                    Remove
                                </Link>
                            </td>
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function InvitationsTable({
    invitations,
    organizationId,
    base,
    canManage,
}: {
    invitations: InvitationRow[];
    organizationId: string;
    base: string;
    canManage: boolean;
}) {
    const pending = invitations.filter((i) => i.status === 'pending');
    if (pending.length === 0) return <EmptyRow />;

    return (
        <table className="w-full text-left text-sm">
            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                <tr>
                    <th className="py-2 pr-4">Email</th>
                    <th className="py-2 pr-4">Role</th>
                    <th className="py-2 pr-4">Expires</th>
                    {canManage && <th className="py-2 pr-4">Actions</th>}
                </tr>
            </thead>
            <tbody>
                {pending.map((inv) => (
                    <tr
                        key={inv.id}
                        className="border-border border-b last:border-0"
                    >
                        <td className="py-2 pr-4">{inv.email}</td>
                        <td className="py-2 pr-4">{inv.role.toLowerCase()}</td>
                        <td className="py-2 pr-4">
                            {new Date(inv.expires_at).toLocaleDateString(
                                'en-GB',
                            )}
                        </td>
                        {canManage && (
                            <td className="py-2 pr-4">
                                <Link
                                    href={`${base}/organizations/${organizationId}/invitations/${inv.id}/resend`}
                                    method="post"
                                    as="button"
                                    className="text-accent text-xs font-medium hover:underline"
                                >
                                    Resend
                                </Link>
                            </td>
                        )}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

function NotesSection({
    notes,
    organizationId,
    base,
}: {
    notes: NoteRow[];
    organizationId: string;
    base: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        body: '',
        category: 'GENERAL',
        pinned: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/organizations/${organizationId}/notes`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <div>
            <form onSubmit={submit} className="mb-4 space-y-2">
                <Textarea
                    placeholder="Leave a note…"
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                />
                <FieldError>{errors.body}</FieldError>
                <div className="flex items-center gap-3">
                    <Select
                        value={data.category}
                        onChange={(e) => setData('category', e.target.value)}
                        className="w-40"
                    >
                        <option value="GENERAL">General</option>
                        <option value="SUPPORT">Support</option>
                        <option value="RISK">Risk</option>
                        <option value="BILLING">Billing</option>
                    </Select>
                    <label className="text-text-secondary flex items-center gap-1.5 text-xs">
                        <input
                            type="checkbox"
                            checked={data.pinned}
                            onChange={(e) =>
                                setData('pinned', e.target.checked)
                            }
                        />
                        Pinned
                    </label>
                    <Button type="submit" size="sm" disabled={processing}>
                        {processing ? 'Adding…' : 'Add note'}
                    </Button>
                </div>
            </form>

            {notes.length === 0 ? (
                <EmptyRow />
            ) : (
                <ul className="space-y-3">
                    {notes.map((note) => (
                        <li
                            key={note.id}
                            className="border-border border-b pb-3 last:border-0"
                        >
                            <div className="mb-1 flex items-center gap-2">
                                <Badge variant="neutral" showGlyph={false}>
                                    {note.category.toLowerCase()}
                                </Badge>
                                {note.pinned && (
                                    <Badge variant="navy" showGlyph={false}>
                                        pinned
                                    </Badge>
                                )}
                                <span className="text-text-secondary text-xs">
                                    {note.author_name} ·{' '}
                                    {new Date(note.created_at).toLocaleString(
                                        'en-GB',
                                    )}
                                </span>
                            </div>
                            <p className="text-primary text-sm whitespace-pre-wrap">
                                {note.body}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function ErasureCard({
    organization,
    base,
}: {
    organization: OrganizationDetail;
    base: string;
}) {
    const [confirmSlug, setConfirmSlug] = useState('');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/organizations/${organization.id}/erase`,
            { confirm_slug: confirmSlug },
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.confirm_slug ?? null),
                onFinish: () => setProcessing(false),
                onSuccess: () => setConfirmSlug(''),
            },
        );
    };

    return (
        <Card className="border-error/40">
            <CardTitle className="text-error mb-3">GDPR erasure</CardTitle>
            <p className="text-text-secondary mb-3 text-sm">
                Anonymizes member accounts exclusive to this organization,
                removes pending invitations, and erases the pilot application
                contact. Operational and financial records are retained per
                statutory retention requirements. This cannot be undone.
            </p>
            <form onSubmit={submit} className="flex items-end gap-3">
                <div className="flex-1">
                    <Label htmlFor="confirm_slug">
                        Type &ldquo;{organization.slug}&rdquo; to confirm
                    </Label>
                    <Input
                        id="confirm_slug"
                        value={confirmSlug}
                        onChange={(e) => setConfirmSlug(e.target.value)}
                    />
                    <FieldError>{error}</FieldError>
                </div>
                <Button
                    type="submit"
                    variant="destructive"
                    size="sm"
                    disabled={processing || confirmSlug !== organization.slug}
                >
                    {processing ? 'Erasing…' : 'Erase personal data'}
                </Button>
            </form>
        </Card>
    );
}

Show.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
