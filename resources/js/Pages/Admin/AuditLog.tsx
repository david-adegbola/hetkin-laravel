import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Input, Label, Select } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { useAdminBasePath } from '@/lib/subdomain';
import { Link, router } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

interface AuditEntry {
    id: string;
    organization_id: string | null;
    organization_name: string | null;
    actor_type: string;
    actor_id: string;
    action: string;
    entity_type: string;
    entity_id: string;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

interface Filters {
    organization_id: string | null;
    actor_type: string | null;
    entity_type: string | null;
    action: string | null;
    from: string | null;
    to: string | null;
}

interface AuditLogProps {
    entries: AuditEntry[];
    total: number;
    page: number;
    page_size: number;
    filters: Filters;
    filtered_organization_name: string | null;
    integrity: { valid: boolean; brokenAtEntryId: string | null };
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/audit-log/page.tsx —
 * fully read-only, no role gate. The hash-chain integrity banner runs
 * unconditionally on every page load, matching v2 (no "verify" button).
 */
export default function AuditLog({
    entries,
    total,
    page,
    page_size,
    filters,
    filtered_organization_name,
    integrity,
}: AuditLogProps) {
    const base = useAdminBasePath();
    const [form, setForm] = useState({
        organization_id: filters.organization_id ?? '',
        actor_type: filters.actor_type ?? '',
        entity_type: filters.entity_type ?? '',
        action: filters.action ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        router.get(`${base}/audit-log`, form, { preserveState: true });
    };

    const totalPages = Math.max(1, Math.ceil(total / page_size));

    return (
        <div>
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Audit Log
                </h1>
                <p className="text-text-secondary text-sm">
                    {total} entries
                    {filtered_organization_name &&
                        ` · filtered to ${filtered_organization_name}`}
                </p>
            </div>

            <div
                className={`rounded-card mb-6 border p-4 text-sm ${
                    integrity.valid
                        ? 'border-success/40 bg-success-subtle text-success'
                        : 'border-error/40 bg-error-subtle text-error'
                }`}
            >
                {integrity.valid
                    ? 'Hash chain intact — no gaps or tampering detected.'
                    : `Hash chain BROKEN at entry ${integrity.brokenAtEntryId}.`}
            </div>

            <Card className="mb-6">
                <form onSubmit={submit} className="grid gap-3 sm:grid-cols-6">
                    <div>
                        <Label htmlFor="organization_id">Organization ID</Label>
                        <Input
                            id="organization_id"
                            value={form.organization_id}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    organization_id: e.target.value,
                                })
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="actor_type">Actor type</Label>
                        <Select
                            id="actor_type"
                            value={form.actor_type}
                            onChange={(e) =>
                                setForm({ ...form, actor_type: e.target.value })
                            }
                        >
                            <option value="">Any</option>
                            <option value="USER">User</option>
                            <option value="ADMIN_USER">Admin user</option>
                            <option value="SYSTEM">System</option>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="entity_type">Entity type</Label>
                        <Input
                            id="entity_type"
                            value={form.entity_type}
                            onChange={(e) =>
                                setForm({
                                    ...form,
                                    entity_type: e.target.value,
                                })
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="action">Action contains</Label>
                        <Input
                            id="action"
                            value={form.action}
                            onChange={(e) =>
                                setForm({ ...form, action: e.target.value })
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="from">From</Label>
                        <Input
                            id="from"
                            type="date"
                            value={form.from}
                            onChange={(e) =>
                                setForm({ ...form, from: e.target.value })
                            }
                        />
                    </div>
                    <div>
                        <Label htmlFor="to">To</Label>
                        <Input
                            id="to"
                            type="date"
                            value={form.to}
                            onChange={(e) =>
                                setForm({ ...form, to: e.target.value })
                            }
                        />
                    </div>
                    <div className="sm:col-span-6">
                        <Button type="submit" size="sm">
                            Filter
                        </Button>
                    </div>
                </form>
            </Card>

            {entries.length === 0 ? (
                <div className="border-border bg-background rounded-card text-text-secondary border p-8 text-center text-sm">
                    No matching entries.
                </div>
            ) : (
                <div className="border-border bg-background rounded-card overflow-x-auto border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                            <tr>
                                <th className="px-4 py-3">Action</th>
                                <th className="px-4 py-3">Actor</th>
                                <th className="px-4 py-3">Entity</th>
                                <th className="px-4 py-3">Organization</th>
                                <th className="px-4 py-3">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            {entries.map((entry) => (
                                <tr
                                    key={entry.id}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="px-4 py-3 font-mono text-xs">
                                        {entry.action}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant="neutral"
                                            showGlyph={false}
                                        >
                                            {entry.actor_type.toLowerCase()}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {entry.entity_type}
                                    </td>
                                    <td className="px-4 py-3">
                                        {entry.organization_id ? (
                                            <Link
                                                href={`${base}/organizations/${entry.organization_id}`}
                                                className="text-accent hover:underline"
                                            >
                                                {entry.organization_name ??
                                                    entry.organization_id}
                                            </Link>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-xs">
                                        {new Date(
                                            entry.created_at,
                                        ).toLocaleString('en-GB')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {totalPages > 1 && (
                <div className="mt-4 flex items-center justify-between text-sm">
                    <span className="text-text-secondary">
                        Page {page} of {totalPages}
                    </span>
                    <div className="flex gap-2">
                        {page > 1 && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.get(
                                        `${base}/audit-log`,
                                        { ...form, page: page - 1 },
                                        { preserveState: true },
                                    )
                                }
                                className="text-accent font-medium hover:underline"
                            >
                                Previous
                            </button>
                        )}
                        {page < totalPages && (
                            <button
                                type="button"
                                onClick={() =>
                                    router.get(
                                        `${base}/audit-log`,
                                        { ...form, page: page + 1 },
                                        { preserveState: true },
                                    )
                                }
                                className="text-accent font-medium hover:underline"
                            >
                                Next
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

AuditLog.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
