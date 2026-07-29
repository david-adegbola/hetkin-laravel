import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardTitle } from '@/Components/ui/Card';
import { FieldError, Input, Label, Select } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { useAdminBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, ReactElement, useState } from 'react';

interface AdminRow {
    id: string;
    name: string;
    email: string;
    role: string;
    active: boolean;
    created_at: string;
}

/**
 * Shows the just-created temp password exactly once, from the "created_
 * admin" flash prop — mirrors v2's CreateAdminUserForm.tsx: never emailed,
 * never logged, gone on the next navigation.
 */
function TempPasswordBanner() {
    const { flash } = usePage<PageProps>().props;
    const [copied, setCopied] = useState(false);

    if (!flash.created_admin) return null;
    const { email, temp_password } = flash.created_admin;

    return (
        <Card className="bg-accent-subtle border-accent/40 mb-6">
            <CardTitle className="mb-2">Account created for {email}</CardTitle>
            <p className="text-text-secondary mb-3 text-sm">
                Copy this temporary password now — it will not be shown again.
            </p>
            <div className="flex items-center gap-3">
                <code className="rounded-control border-border bg-background flex-1 border px-3 py-2 text-sm">
                    {temp_password}
                </code>
                <Button
                    type="button"
                    size="sm"
                    variant="secondary"
                    onClick={() => {
                        navigator.clipboard?.writeText(temp_password);
                        setCopied(true);
                    }}
                >
                    {copied ? 'Copied' : 'Copy'}
                </Button>
            </div>
        </Card>
    );
}

function CreateAdminForm({ base }: { base: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: 'SUPPORT',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`${base}/team`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <Card className="mb-6">
            <CardTitle className="mb-3">Create admin account</CardTitle>
            <form
                onSubmit={submit}
                className="grid gap-3 sm:grid-cols-4 sm:items-end"
            >
                <div>
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <FieldError>{errors.name}</FieldError>
                </div>
                <div>
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <FieldError>{errors.email}</FieldError>
                </div>
                <div>
                    <Label htmlFor="role">Role</Label>
                    <Select
                        id="role"
                        value={data.role}
                        onChange={(e) => setData('role', e.target.value)}
                    >
                        <option value="ADMIN">Admin</option>
                        <option value="VERIFICATION_ANALYST">
                            Verification Analyst
                        </option>
                        <option value="SUPPORT">Support</option>
                        <option value="AUDITOR">Auditor</option>
                    </Select>
                </div>
                <Button type="submit" size="sm" disabled={processing}>
                    {processing ? 'Creating…' : 'Create account'}
                </Button>
            </form>
        </Card>
    );
}

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/team/page.tsx. Only
 * ADMIN sees the create form and role-change/deactivate/reactivate
 * controls; every other role sees a read-only roster (server-enforced
 * regardless, see routes/admin-protected.php's `admin.role:ADMIN` group).
 */
export default function Team({ admins }: { admins: AdminRow[] }) {
    const base = useAdminBasePath();
    const { auth } = usePage<PageProps>().props;
    const self = auth.admin;
    const isAdmin = self?.role === 'ADMIN';

    return (
        <div>
            <Head title="Team" />
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Team
                </h1>
                <p className="text-text-secondary text-sm">
                    {admins.length} admin account(s)
                </p>
            </div>

            <TempPasswordBanner />

            {isAdmin && <CreateAdminForm base={base} />}

            <div className="border-border bg-background rounded-card overflow-x-auto border">
                <table className="w-full text-left text-sm">
                    <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                        <tr>
                            <th className="px-4 py-3">Name</th>
                            <th className="px-4 py-3">Email</th>
                            <th className="px-4 py-3">Role</th>
                            <th className="px-4 py-3">Status</th>
                            {isAdmin && <th className="px-4 py-3">Actions</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {admins.map((admin) => (
                            <tr
                                key={admin.id}
                                className="border-border border-b last:border-0"
                            >
                                <td className="px-4 py-3">{admin.name}</td>
                                <td className="px-4 py-3">{admin.email}</td>
                                <td className="px-4 py-3">
                                    {isAdmin && admin.id !== self?.id ? (
                                        <Select
                                            defaultValue={admin.role}
                                            onChange={(e) =>
                                                router.post(
                                                    `${base}/team/${admin.id}/role`,
                                                    { role: e.target.value },
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <option value="ADMIN">Admin</option>
                                            <option value="VERIFICATION_ANALYST">
                                                Verification Analyst
                                            </option>
                                            <option value="SUPPORT">
                                                Support
                                            </option>
                                            <option value="AUDITOR">
                                                Auditor
                                            </option>
                                        </Select>
                                    ) : (
                                        admin.role
                                            .toLowerCase()
                                            .replace(/_/g, ' ')
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <Badge
                                        variant={
                                            admin.active ? 'cheap' : 'expensive'
                                        }
                                        showGlyph={false}
                                    >
                                        {admin.active
                                            ? 'active'
                                            : 'deactivated'}
                                    </Badge>
                                </td>
                                {isAdmin && (
                                    <td className="px-4 py-3">
                                        {admin.id === self?.id ? (
                                            '—'
                                        ) : admin.active ? (
                                            <Link
                                                href={`${base}/team/${admin.id}/deactivate`}
                                                method="post"
                                                as="button"
                                                className="text-error text-xs font-medium hover:underline"
                                            >
                                                Deactivate
                                            </Link>
                                        ) : (
                                            <Link
                                                href={`${base}/team/${admin.id}/reactivate`}
                                                method="post"
                                                as="button"
                                                className="text-accent text-xs font-medium hover:underline"
                                            >
                                                Reactivate
                                            </Link>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

Team.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
