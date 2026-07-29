import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { Head, Link } from '@inertiajs/react';

const TIMELINE = [
    {
        n: 1,
        title: 'Apply',
        body: "Tell us about your process, your flexibility, and what you're hoping to get out of a pilot.",
    },
    {
        n: 2,
        title: 'Qualification call',
        body: 'A short call to confirm fit — your production setup, data availability, and timeline.',
    },
    {
        n: 3,
        title: 'Onboarding',
        body: 'We connect your consumption data and your production schedule.',
    },
    {
        n: 4,
        title: 'Savings assessment',
        body: 'A transparent report: your current cost, an optimized schedule, and the full calculation behind it.',
    },
    {
        n: 5,
        title: 'Decide',
        body: 'Review the assessment and decide whether to move forward — no obligation from an application.',
    },
];

const METRICS = [
    { label: 'Current cost', value: '€148,200' },
    { label: 'Optimized cost', value: '€121,900' },
    { label: 'Estimated savings', value: '€26,300' },
    { label: 'Net customer savings*', value: '€19,700' },
];

export default function PilotProgramIndex() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Pilot Program" />
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 pt-16 pb-4 text-center">
                    <div className="mx-auto max-w-[820px]">
                        <Eyebrow>Pilot Program</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            Five steps from application to a savings figure you
                            can act on
                        </h1>
                        <p className="text-ink-500 mx-auto mt-4 max-w-[600px] text-sm">
                            Applications are reviewed individually — no
                            self-serve signup. Here&rsquo;s exactly what happens
                            after you apply.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-14">
                    <div className="relative mx-auto max-w-[1000px]">
                        <div className="border-line-100 absolute top-5 right-0 left-0 hidden border-t sm:block" />
                        <div className="grid gap-8 sm:grid-cols-5">
                            {TIMELINE.map((step) => (
                                <div
                                    key={step.n}
                                    className="relative text-center sm:text-left"
                                >
                                    <div className="bg-cheap-100 text-cheap-500 font-display border-background relative z-10 mx-auto flex h-10 w-10 items-center justify-center rounded-full border-4 text-sm font-bold sm:mx-0">
                                        {step.n}
                                    </div>
                                    <h3 className="font-display text-navy-900 mt-3 text-sm font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="text-ink-500 mt-1 text-sm">
                                        {step.body}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="border-line-100 bg-surface-0 border-y px-6 py-16">
                    <div className="mx-auto max-w-[600px]">
                        <div className="border-line-100 bg-paper-0 rounded-card border p-6 shadow-sm">
                            <h3 className="font-display text-navy-900 text-sm font-semibold">
                                Sample Savings Assessment
                            </h3>
                            <div className="mt-4 grid grid-cols-2 gap-4">
                                {METRICS.map((m) => (
                                    <div key={m.label}>
                                        <p className="text-ink-500 text-xs">
                                            {m.label}
                                        </p>
                                        <p className="font-display text-navy-900 tabular-nums-figure text-lg font-semibold">
                                            {m.value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                        <p className="text-ink-500 mt-3 text-center text-xs">
                            *Illustrative example, not a guaranteed outcome. Net
                            of Hetkin&rsquo;s performance fee.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-16 text-center">
                    <Link
                        href={route('pilot-program.apply')}
                        className={buttonVariants({ size: 'lg' })}
                    >
                        Apply now
                    </Link>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
