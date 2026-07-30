import { Logo } from '@/Components/Logo';
import { InstallPwaButton } from '@/Components/pwa/InstallPwaButton';
import { PwaUpdateBanner } from '@/Components/pwa/PwaUpdateBanner';
import { buttonVariants } from '@/Components/ui/Button';
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
 * Mirrors the Next.js app's src/app/admin/(protected)/layout.tsx's top nav
 * — the full ten-destination list now that Stage 9 has built a real page
 * behind every one of them. `Docs` is the one exception: the Explore
 * agent's own Stage 9 research flagged it as low-priority static content,
 * so it's a single simple page (resources/js/Pages/Admin/Docs.tsx), not a
 * data-backed console screen.
 */
const NAV_ITEMS: Array<{ href: string; label: string }> = [
    { href: '/overview', label: 'Overview' },
    { href: '/pilots', label: 'Pilots' },
    { href: '/organizations', label: 'Organizations' },
    { href: '/baselines', label: 'Baselines' },
    { href: '/verification', label: 'Verification' },
    { href: '/invoices', label: 'Invoices' },
    { href: '/jobs', label: 'Jobs' },
    { href: '/audit-log', label: 'Audit Log' },
    { href: '/team', label: 'Team' },
    { href: '/docs', label: 'Docs' },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth, url } = usePage<PageProps & { url?: string }>().props;
    const admin = auth.admin;
    const base = useAdminBasePath();
    const currentPath =
        typeof window !== 'undefined' ? window.location.pathname : (url ?? '');

    return (
        <div className="bg-background min-h-screen">
            {/*
                Stage 15 accessibility pass: the ten-item nav below (plus
                install/account controls) meant every single page load
                forced a keyboard/screen-reader user to tab through all of
                it before reaching page content — WCAG 2.4.1 (Bypass
                Blocks). `sr-only focus:not-sr-only` is the standard
                pattern: invisible until it's the focused element, then a
                normal visible, tabbable link.
            */}
            <a
                href="#admin-main-content"
                className="focus:bg-accent focus:rounded-control sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:px-4 focus:py-2 focus:text-white"
            >
                Skip to main content
            </a>
            <nav className="border-border bg-background flex flex-wrap items-center gap-4 border-b px-8 py-3">
                <Link href={`${base}/overview`} className="flex items-center">
                    <Logo />
                </Link>
                <div className="flex flex-1 flex-wrap gap-1">
                    {NAV_ITEMS.map((item) => {
                        const active = currentPath.endsWith(item.href);
                        return (
                            <Link
                                key={item.href}
                                href={`${base}${item.href}`}
                                className={
                                    active
                                        ? 'rounded-control bg-accent-subtle text-accent px-3 py-2 text-sm font-medium'
                                        : 'rounded-control text-text-secondary hover:bg-surface hover:text-primary px-3 py-2 text-sm font-medium'
                                }
                            >
                                {item.label}
                            </Link>
                        );
                    })}
                </div>
                <InstallPwaButton surface="admin" />
                {admin && (
                    <div className="text-text-secondary flex items-center gap-3 text-xs">
                        <span>
                            <strong className="text-primary">
                                {admin.name}
                            </strong>{' '}
                            · {ADMIN_ROLE_LABELS[admin.role] ?? admin.role}
                        </span>
                        <Link
                            href={`${base}/logout`}
                            method="post"
                            as="button"
                            className={buttonVariants({
                                variant: 'ghost',
                                size: 'sm',
                            })}
                        >
                            Sign out
                        </Link>
                    </div>
                )}
            </nav>
            <main id="admin-main-content" className="p-8">
                {children}
            </main>
            <PwaUpdateBanner />
        </div>
    );
}
