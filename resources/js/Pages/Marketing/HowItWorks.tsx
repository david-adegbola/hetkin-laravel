import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { Head, Link } from '@inertiajs/react';

const REASONS = [
    {
        title: 'Schedules optimize for delivery, not price',
        body: 'Planners are measured on on-time-in-full, not on electricity spend — so price never enters the scheduling decision, even when it should.',
    },
    {
        title: 'Flat-rate thinking, dynamic-price reality',
        body: 'Many industrial contracts were built for an era of stable tariffs. Hourly spot pricing rewards flexibility that most schedules were never designed to offer.',
    },
    {
        title: 'Price data and production data live in different systems',
        body: "Nobody's ERP talks to Nord Pool. Connecting the two by hand, every day, isn't a realistic ask of a production planner.",
    },
];

const STEPS = [
    {
        n: 1,
        title: 'Connect your data',
        body: 'Meter data via consent-based Fingrid Datahub access, or manual upload. Your production schedule, however you already keep it.',
    },
    {
        n: 2,
        title: 'Get your savings assessment',
        body: 'A transparent report showing your current cost, an optimized schedule, and exactly how the estimate was calculated.',
    },
    {
        n: 3,
        title: 'Follow the recommendations you choose',
        body: 'Every suggestion shows projected savings alongside delivery-risk. Accept, reject, or partially adopt — you stay in control.',
    },
    {
        n: 4,
        title: 'We verify savings, then invoice',
        body: "Realized savings are measured against a locked baseline every month. Hetkin's fee is a share of what's actually saved — with the full calculation shown.",
    },
    {
        n: 5,
        title: 'Keep improving',
        body: "As your schedule, contracts, or the market shift, recommendations update — this isn't a one-time report, it's ongoing.",
    },
    {
        n: 6,
        title: 'Only pay for results',
        body: "A small platform fee covers delivery; the rest of what you pay is a share of verified savings. If there's no saving, there's no performance fee.",
    },
];

export default function HowItWorks() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="How it works" />
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 pt-16 pb-4 text-center">
                    <div className="mx-auto max-w-[900px]">
                        <Eyebrow>The problem</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            Most production schedules are built around delivery
                            dates and shift patterns — never around what
                            electricity actually costs that hour.
                        </h1>
                        <p className="font-display text-navy-900 mx-auto mt-5 max-w-2xl text-lg font-semibold">
                            Finnish day-ahead prices have swung from below
                            −€500/MWh to well over €500/MWh in the same week,
                            and 2026 forward prices are trading above
                            2025&apos;s realized average — the spread isn&apos;t
                            shrinking.
                        </p>
                        <p className="text-ink-500 mt-2 text-xs">
                            Nord Pool FI day-ahead market data, 2023–2026.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-16">
                    <div className="mx-auto max-w-[1200px]">
                        <div className="text-center">
                            <Eyebrow>Why this happens</Eyebrow>
                            <h2 className="font-display text-navy-900 text-2xl font-semibold sm:text-3xl">
                                It&apos;s not a discipline problem. It&apos;s a
                                visibility problem.
                            </h2>
                        </div>
                        <div className="mt-10 grid gap-6 sm:grid-cols-3">
                            {REASONS.map((r) => (
                                <Card key={r.title}>
                                    <h3 className="font-display text-navy-900 text-lg font-semibold">
                                        {r.title}
                                    </h3>
                                    <p className="text-ink-500 mt-2 text-sm">
                                        {r.body}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="border-line-100 bg-surface-0 border-y px-6 py-16">
                    <div className="mx-auto max-w-[1200px]">
                        <div className="text-center">
                            <Eyebrow>How Hetkin works</Eyebrow>
                            <h2 className="font-display text-navy-900 text-2xl font-semibold sm:text-3xl">
                                Six steps. No new equipment, no new software to
                                learn on the floor.
                            </h2>
                        </div>
                        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
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

                <section className="px-6 py-16 text-center">
                    <Link
                        href={route('pilot-program.apply')}
                        className={buttonVariants({ size: 'lg' })}
                    >
                        Join the Pilot
                    </Link>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
