import { Logo } from '@/Components/Logo';
import NavLink from '@/Components/portal/NavLink';
import { usePortalBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { CalendarClock, Database, LayoutDashboard, Menu } from 'lucide-react';
import { ReactNode } from 'react';

/**
 * Mirrors the Next.js app's PROCESS_TYPE_LABELS (src/lib/pilot.ts) — the
 * sidebar footer's only consumer of it, so kept local rather than a
 * separate module.
 */
const PROCESS_TYPE_LABELS: Record<string, string> = {
    COLD_STORAGE: 'Cold storage',
    METAL_TREATMENT: 'Metal / heat treatment',
    PLASTICS_MOLDING: 'Plastics injection molding',
    WOOD_PROCESSING: 'Wood processing',
    WATER_TREATMENT: 'Water treatment',
    FOOD_PROCESSING: 'Food processing',
    CHEMICAL_BATCH: 'Chemical / battery batch processing',
    OTHER: 'Other / not sure',
};

const LOGO_LINK_CLASSES =
    'flex items-center gap-2 rounded-control transition-opacity duration-150 ease-out hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 active:opacity-70';

/**
 * Portal chrome — sidebar + topbar. A persistent Inertia layout (assigned
 * via each page's `Page.layout`, see Pages/Portal/*.tsx), not page-local
 * JSX — mirrors exactly why v2 moved PortalShell into (portal)/layout.tsx:
 * beta feedback that page-to-page navigation felt slow and jarring, root
 * cause being the sidebar fully unmounting/remounting on every click. A
 * persistent layout means Inertia only swaps `children` on navigation.
 *
 * Nav items are a deliberate SUBSET of v2's full sidebar (which also has
 * Reports, Billing, Team & Permissions, Support) — this Laravel port is
 * itself mid-migration, and those pages don't exist yet (their own later
 * stages). Showing a nav link to a 404 would be worse than v2's actual
 * behavior, not equivalent to it, so the nav only lists what Stage 5
 * actually built. See MIGRATION_PLAN.md ADR-33; extend this list as later
 * stages add real pages, the same way routes/portal.php is extended.
 */
export default function PortalLayout({ children }: { children: ReactNode }) {
    const { organization } = usePage<PageProps>().props;
    const base = usePortalBasePath();
    const homeHref = base || '/';

    const navItems = [
        {
            href: homeHref,
            label: 'Dashboard',
            exact: true,
            icon: (
                <LayoutDashboard
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: `${base}/schedule`,
            label: 'Schedule',
            exact: false,
            icon: (
                <CalendarClock
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: `${base}/data`,
            label: 'Data',
            exact: false,
            icon: (
                <Database
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
    ];

    return (
        <div className="bg-background min-h-screen md:grid md:grid-cols-[232px_1fr]">
            <input
                type="checkbox"
                id="portal-nav-toggle"
                className="peer hidden"
            />

            <div className="border-border bg-background flex items-center justify-between border-b px-4 py-3 md:hidden">
                <Link href={homeHref} className={LOGO_LINK_CLASSES}>
                    <Logo />
                </Link>
                <label
                    htmlFor="portal-nav-toggle"
                    className="rounded-control text-primary hover:bg-surface flex h-11 w-11 cursor-pointer items-center justify-center"
                    aria-label="Toggle navigation menu"
                >
                    <Menu aria-hidden className="h-5 w-5" strokeWidth={2} />
                </label>
            </div>

            <aside className="border-border bg-background hidden flex-col gap-1 border-r p-4 peer-checked:flex md:flex">
                <Link
                    href={homeHref}
                    className={`${LOGO_LINK_CLASSES} hidden px-2.5 pt-2 pb-5 md:flex`}
                >
                    <Logo />
                </Link>
                {navItems.map((item) => (
                    <NavLink
                        key={item.href}
                        href={item.href}
                        icon={item.icon}
                        exact={item.exact}
                    >
                        {item.label}
                    </NavLink>
                ))}
                {organization && (
                    <div className="border-border text-text-secondary mt-auto border-t px-3 py-3 text-xs">
                        {organization.name}
                        <br />
                        {organization.status === 'PILOT'
                            ? 'Pilot'
                            : organization.status}
                        {organization.process_type
                            ? ` · ${PROCESS_TYPE_LABELS[organization.process_type]}`
                            : ''}
                    </div>
                )}
            </aside>

            <main className="px-4 py-6 pb-12 md:px-8">{children}</main>
        </div>
    );
}
