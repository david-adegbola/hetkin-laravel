import { Logo } from '@/Components/Logo';
import { buttonVariants } from '@/Components/ui/Button';
import { Link } from '@inertiajs/react';

/**
 * Marketing-site header — ported 1:1 from the Next.js app's
 * src/components/SiteHeader.tsx, used directly on each marketing page
 * (Stage 12) rather than via a persistent Inertia layout, matching v2's
 * own choice to inline it per-page rather than via a Next.js layout route
 * group.
 *
 * "Sign In" always points at this app's own `/sign-in` (route `login`)
 * rather than v2's `{NEXT_PUBLIC_DASHBOARD_URL}/sign-in` absolute-URL
 * trick — that trick exists in v2 to route around Vercel's specific
 * proxy setup (src/proxy.ts) for a subdomain that doesn't share cookies
 * with the apex domain by default. This port's architecture doesn't have
 * that problem: `SESSION_DOMAIN` (MIGRATION_PLAN.md ADR-21) already
 * shares the session cookie across the apex and every subdomain, so
 * signing in from the apex domain's own `/sign-in` works correctly
 * everywhere, including when `dashboard.*` is live — the same behavior
 * v2's own "local dev, or any environment without the subdomain wired
 * up" fallback path already describes, just without needing the
 * fallback logic at all here.
 */
export default function SiteHeader() {
    return (
        <header className="border-border bg-background/90 sticky top-0 z-50 border-b backdrop-blur">
            <nav className="mx-auto flex h-18 max-w-[1200px] items-center justify-between px-6 py-4">
                <Link
                    href={route('home')}
                    className="rounded-control focus-visible:ring-accent focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <Logo />
                </Link>
                <div className="text-text-secondary hidden items-center gap-7 text-sm font-medium md:flex">
                    <Link
                        href={route('how-it-works')}
                        className="hover:text-primary"
                    >
                        How it works
                    </Link>
                    <Link
                        href={route('pricing')}
                        className="hover:text-primary"
                    >
                        Pricing
                    </Link>
                    <Link
                        href={route('pilot-program.index')}
                        className="hover:text-primary"
                    >
                        Pilot Program
                    </Link>
                    <Link href={route('trust')} className="hover:text-primary">
                        Trust &amp; Security
                    </Link>
                    <Link href={route('faq')} className="hover:text-primary">
                        FAQ
                    </Link>
                </div>
                <div className="flex items-center gap-2">
                    <Link
                        href={route('login')}
                        className={buttonVariants({
                            variant: 'ghost',
                            size: 'sm',
                        })}
                    >
                        Sign In
                    </Link>
                    <Link
                        href={route('pilot-program.apply')}
                        className={buttonVariants({ size: 'sm' })}
                    >
                        Join the Pilot
                    </Link>
                </div>
            </nav>
        </header>
    );
}
