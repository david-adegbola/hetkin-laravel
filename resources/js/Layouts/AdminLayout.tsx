import { Logo } from '@/Components/Logo';
import { Button } from '@/Components/ui/Button';
import { useAdminBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

const ADMIN_ROLE_LABELS: Record<string, string> = {
    ADMIN: 'Admin',
    VERIFICATION_ANALYST: 'Verification Analyst',
    SUPPORT: 'Support',
    AUDITOR: 'Auditor',
};

/**
 * Internal admin shell — mirrors the Next.js app's src/app/admin/
 * (protected)/layout.tsx in spirit, not in full: that layout's top nav
 * lists ten destinations (Overview, Pilots, Organizations, Baselines,
 * Verification, Invoices, Jobs, Audit Log, Team, Docs); only "Pilots" has
 * a real page behind it so far (Stage 8) — the rest is the unified admin
 * console, Stage 9. Same "honest subset" reasoning as
 * resources/js/Layouts/PortalLayout.tsx (MIGRATION_PLAN.md ADR-33),
 * applied to the admin realm for the first time — a nav link to a 404
 * would be worse than v2's actual behavior, not equivalent to it. Extend
 * this nav list the same way PortalLayout's was extended, as Stage 9
 * builds each real page.
 */
export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage<PageProps>().props;
    const admin = auth.admin;
    const base = useAdminBasePath();

    return (
        <div className="bg-background min-h-screen">
            <nav className="border-border bg-background flex flex-wrap items-center gap-4 border-b px-8 py-3">
                <Link href={base || '/'} className="flex items-center">
                    <Logo />
                </Link>
                <div className="flex flex-1 flex-wrap gap-1">
                    <Link
                        href={`${base}/pilots`}
                        className="rounded-control bg-accent-subtle text-accent px-3 py-2 text-sm font-medium"
                    >
                        Pilots
                    </Link>
                </div>
                {admin && (
                    <div className="text-text-secondary flex items-center gap-3 text-xs">
                        <span>
                            <strong className="text-primary">
                                {admin.name}
                            </strong>{' '}
                            · {ADMIN_ROLE_LABELS[admin.role] ?? admin.role}
                        </span>
                        <Link href={`${base}/logout`} method="post" as="button">
                            <Button variant="ghost" size="sm">
                                Sign out
                            </Button>
                        </Link>
                    </div>
                )}
            </nav>
            <div className="p-8">{children}</div>
        </div>
    );
}
