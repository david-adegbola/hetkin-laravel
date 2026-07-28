import { Spinner } from '@/Components/ui/Spinner';
import { Link, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';

/**
 * Sidebar nav link with instant tap feedback — ports the Next.js app's
 * NavLink.tsx exactly, including the reasoning behind it: beta feedback
 * ("there should be an activity indicator after selecting an icon... you
 * end up tapping it again since you think it's your network") is why this
 * shows a spinner the instant a click registers, not just whenever the
 * destination page finally renders.
 *
 * Uses Inertia's per-Link onStart/onFinish events rather than Next's
 * useLinkStatus() (no equivalent hook here) — same effect: true exactly
 * while a visit to THIS link's href is in flight. resources/js/app.tsx
 * disables Inertia's own global progress bar specifically because this
 * per-link treatment already covers the feedback gap and a top-of-page
 * bar would be a new UI element v2 never had.
 */
export default function NavLink({
    href,
    icon,
    exact = false,
    children,
}: {
    href: string;
    icon: ReactNode;
    /**
     * The home item's href is itself a PREFIX of every other item's href
     * whenever the app is reached via its prefixed path (base="/dashboard"
     * — i.e. accessed directly on the main domain rather than the
     * dashboard.hetkin.app subdomain) — without `exact`, "Dashboard" would
     * show active on every sub-page too.
     */
    exact?: boolean;
    children: ReactNode;
}) {
    const { url } = usePage();
    const [pending, setPending] = useState(false);
    const active = exact
        ? url === href
        : url === href || url.startsWith(`${href}/`);

    return (
        <Link
            href={href}
            onStart={() => setPending(true)}
            onFinish={() => setPending(false)}
            aria-current={active ? 'page' : undefined}
            className={`rounded-control relative flex min-h-11 items-center gap-2.5 px-3 py-3 text-sm transition-colors duration-150 ease-out active:opacity-80 ${
                active
                    ? 'bg-accent-subtle text-accent font-medium'
                    : 'text-primary hover:bg-surface font-normal'
            }`}
        >
            {icon}
            <span className={pending ? 'flex-1 opacity-60' : 'flex-1'}>
                {children}
            </span>
            <Spinner
                className={
                    pending
                        ? `h-3.5 w-3.5 opacity-100 transition-opacity duration-150 ${active ? 'border-accent/25 border-t-accent' : 'border-border border-t-text-secondary'}`
                        : 'border-border border-t-text-secondary h-3.5 w-3.5 opacity-0 transition-opacity duration-150'
                }
                label="Loading page"
            />
            {pending && (
                <span
                    aria-hidden
                    className="absolute inset-0 cursor-wait"
                    onClick={(e) => e.preventDefault()}
                />
            )}
        </Link>
    );
}
