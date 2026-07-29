import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { Link } from '@inertiajs/react';

/**
 * Home page — hero only for now, matching v2's own documented scope
 * decision (src/app/page.tsx): the full narrative (problem → why → how it
 * works → pilot process → assessment preview → journey → trust → FAQ →
 * final CTA) is a v2 follow-up pass too, not something this port is behind
 * on. No <Head title> — v2's src/app/page.tsx has no `metadata` export
 * either, so the tab title stays the plain default ("Hetkin — Run
 * production when electricity is cheap"), not "Home · Hetkin".
 */
export default function Home() {
    return (
        <div className="flex min-h-screen flex-col">
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 pt-20 pb-12 text-center">
                    <div className="mx-auto max-w-[1200px]">
                        <span className="border-line-100 bg-surface-0 text-navy-700 mb-5 inline-block rounded-full border px-3.5 py-1.5 text-xs font-semibold tracking-wide uppercase">
                            Now piloting with Finnish manufacturers
                        </span>
                        <h1 className="font-display text-navy-900 mx-auto max-w-3xl text-5xl leading-[1.05] font-semibold tracking-tight sm:text-6xl">
                            Same production.
                            <br />
                            Lower electricity bill.
                        </h1>
                        <p className="text-ink-500 mx-auto mt-6 max-w-xl text-lg">
                            Hetkin compares your production schedule against
                            real electricity prices, shows you exactly which
                            shifts to move, and only earns a fee when you
                            actually save money.
                        </p>
                        <div className="mt-8 flex flex-wrap items-center justify-center gap-3.5">
                            <Link
                                href={route('pilot-program.apply')}
                                className={buttonVariants({ size: 'lg' })}
                            >
                                Join the Pilot
                            </Link>
                            <Link
                                href={route('how-it-works')}
                                className={buttonVariants({
                                    variant: 'secondary',
                                    size: 'lg',
                                })}
                            >
                                See how it works
                            </Link>
                        </div>
                        <p className="text-ink-500 mt-3.5 text-sm">
                            No cost to apply. Pilots are reviewed individually.
                        </p>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
