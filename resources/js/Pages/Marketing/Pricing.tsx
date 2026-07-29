import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

/**
 * Deliberately does not state a specific fee amount or revenue-share
 * percentage — Phase 1 §5 specifies the hybrid *model* (base fee +
 * revenue share), not a number, and this codebase's own standing rule
 * (App\Domain\Billing\Actions\IssueInvoiceAction) is to ask before
 * inventing business terms rather than fabricate one for a marketing
 * page.
 */
const PRINCIPLES = [
    {
        title: 'A small base fee',
        body: 'Covers the platform — data connections, the savings assessment, ongoing recommendations. Confirmed with you before your pilot starts, not published as a one-size list price, because it scales with your metering footprint.',
    },
    {
        title: 'A share of verified savings',
        body: 'The rest of what you pay is a percentage of the electricity cost savings we actually verify each month — not projected, not estimated, measured against your locked baseline.',
    },
    {
        title: 'No savings, no performance fee',
        body: "If a month's verified savings come out at zero, the revenue-share portion of that month's bill is zero too. The base fee is the only thing you pay regardless of outcome.",
    },
];

const STEPS = [
    {
        n: 1,
        title: 'We lock a baseline',
        body: "Before your pilot starts, we agree a documented baseline — what your electricity cost would have been without Hetkin. It doesn't move once locked.",
    },
    {
        n: 2,
        title: 'We measure your actual cost',
        body: 'Each month, your real metered consumption against real day-ahead prices gives your actual electricity cost for the period.',
    },
    {
        n: 3,
        title: 'Verified savings = baseline − actual',
        body: 'That gap, scaled to the billing period, is what the revenue share applies to. See our Methodology page for the full calculation and the internal review it goes through before it’s ever billed.',
    },
    {
        n: 4,
        title: 'One invoice, one calculation shown',
        body: 'Your invoice shows the base fee, the verified savings figure, the revenue-share percentage, and the resulting total — the same numbers your finance team could reconstruct from your own meter and price data.',
    },
];

export default function Pricing() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Pricing" />
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 pt-16 pb-4 text-center">
                    <div className="mx-auto max-w-[860px]">
                        <Eyebrow>Pricing</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            We only earn more when you save more.
                        </h1>
                        <p className="text-ink-500 mx-auto mt-5 max-w-2xl text-lg">
                            Hybrid pricing: a small base fee, plus a share of
                            the electricity cost savings we verify each month.
                            Exact figures are confirmed during your pilot
                            application, not listed here as a fixed price — see
                            why below.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-14">
                    <div className="mx-auto max-w-[1100px]">
                        <div className="grid gap-6 sm:grid-cols-3">
                            {PRINCIPLES.map((p) => (
                                <Card key={p.title}>
                                    <h3 className="font-display text-navy-900 text-lg font-semibold">
                                        {p.title}
                                    </h3>
                                    <p className="text-ink-500 mt-2 text-sm">
                                        {p.body}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="border-line-100 bg-surface-0 border-y px-6 py-16">
                    <div className="mx-auto max-w-[1100px]">
                        <div className="text-center">
                            <Eyebrow>How your bill is calculated</Eyebrow>
                            <h2 className="font-display text-navy-900 text-2xl font-semibold sm:text-3xl">
                                Every euro on your invoice traces back to a
                                number you can check yourself.
                            </h2>
                        </div>
                        <div className="mt-10 grid gap-6 sm:grid-cols-2">
                            {STEPS.map((s) => (
                                <Card key={s.n}>
                                    <div className="bg-cheap-100 text-cheap-500 font-display mb-3.5 flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold">
                                        {s.n}
                                    </div>
                                    <h3 className="font-display text-navy-900 text-lg font-semibold">
                                        {s.title}
                                    </h3>
                                    <p className="text-ink-500 mt-2 text-sm">
                                        {s.body}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="px-6 py-14 text-center">
                    <div className="mx-auto max-w-[700px]">
                        <h2 className="font-display text-navy-900 text-xl font-semibold">
                            Why we don&apos;t publish a fixed number
                        </h2>
                        <p className="text-ink-500 mt-3 text-sm">
                            The base fee scales with how many metering points
                            and sites you connect, and both figures are set per
                            pilot rather than off a public rate card — the same
                            way an early design-partner relationship is priced
                            anywhere. We&apos;d rather tell you the real number
                            in a conversation than publish a placeholder that
                            doesn&apos;t hold up once we&apos;ve actually looked
                            at your setup.
                        </p>
                    </div>
                </section>

                <section className="bg-navy-900 px-6 py-16 text-center text-white">
                    <h2 className="font-display text-2xl font-semibold text-white sm:text-3xl">
                        See what your schedule is worth.
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-white/70">
                        Applications are reviewed individually. Tell us about
                        your process and we&apos;ll follow up with your actual
                        pricing and a qualification call.
                    </p>
                    <div className="mt-6 flex flex-wrap items-center justify-center gap-3.5">
                        <Link
                            href={route('pilot-program.apply')}
                            className={cn(
                                buttonVariants({ size: 'lg' }),
                                'bg-background text-primary hover:bg-background/90',
                            )}
                        >
                            Join the Pilot
                        </Link>
                        <Link
                            href={route('methodology')}
                            className={cn(
                                buttonVariants({
                                    variant: 'secondary',
                                    size: 'lg',
                                }),
                                'border-white/30 bg-transparent text-white hover:bg-white/10',
                            )}
                        >
                            How savings are calculated
                        </Link>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
