import { Logo } from '@/Components/Logo';
import NavLink from '@/Components/portal/NavLink';
import { InstallPwaButton } from '@/Components/pwa/InstallPwaButton';
import { PwaUpdateBanner } from '@/Components/pwa/PwaUpdateBanner';
import { usePortalBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CreditCard,
    Database,
    FileText,
    LayoutDashboard,
    LifeBuoy,
    Menu,
    ShieldCheck,
    Users,
} from 'lucide-react';
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
 * Nav items now match v2's full sidebar exactly (Dashboard/Schedule/Data/
 * Reports/Billing/Team & Permissions/Support) — ADR-33 (Stage 5) had
 * deliberately scoped this list down to an honest subset while Team and
 * Support didn't have real pages yet ("a nav link to a 404 is worse than
 * v2's actual behavior"); both now exist (Portal/Team.tsx,
 * Portal/Support.tsx), so the subset restriction no longer applies. See
 * MIGRATION_PLAN.md's post-Stage-12 ADR restoring these.
 *
 * Security is appended after the v2-mirrored items, not interleaved with
 * them — it's this port's own out-of-band Account Security module
 * (MIGRATION_PLAN.md ADR-13..20), with no v2 nav item to match position
 * against, so it goes last rather than guessing where v2 would have put
 * it. Its href is Ziggy's `route('settings.security')`, not
 * `${base}/security` like every other item — that route lives outside the
 * portal's `dashboard`-prefix group entirely (routes/settings.php) and,
 * unlike every portal route, isn't `base`-relative to begin with.
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
        {
            href: `${base}/reports`,
            label: 'Reports',
            exact: false,
            icon: (
                <FileText
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: `${base}/billing`,
            label: 'Billing',
            exact: false,
            icon: (
                <CreditCard
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: `${base}/team`,
            label: 'Team & Permissions',
            exact: false,
            icon: (
                <Users
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: `${base}/support`,
            label: 'Support',
            exact: false,
            icon: (
                <LifeBuoy
                    aria-hidden
                    className="h-4 w-4 flex-shrink-0"
                    strokeWidth={2}
                />
            ),
        },
        {
            href: route('settings.security'),
            label: 'Security',
            exact: false,
            icon: (
                <ShieldCheck
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
                <div className="mt-auto flex flex-col gap-3 px-1">
                    <InstallPwaButton surface="portal" />
                    {organization && (
                        <div className="border-border text-text-secondary border-t px-2 py-3 text-xs">
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
                </div>
            </aside>

            <div className="flex flex-col">
                <main className="flex-1 px-4 py-6 pb-12 md:px-8">
                    {children}
                </main>
                <PwaUpdateBanner />
            </div>
        </div>
    );
}
