import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

/**
 * Describes mechanics that already exist in the codebase
 * (App\Domain\Billing\Services, App\Domain\Optimization, the shadow-mode
 * review in the Admin Verification console) in plain language, rather
 * than marketing copy about "AI-powered optimization." Phase 2's
 * Operator-Owner and Finance personas need "a number they can defend to
 * their own auditor" — this page is written for that reader.
 */
const STAGES = [
    {
        n: 1,
        title: 'Baseline — locked before your pilot starts',
        body: "We build a documented estimate of what your electricity cost would have been without Hetkin's recommendations, from your historical meter data and production pattern. Once locked, it doesn't move — every later comparison is against this same fixed reference, the same way a fixed budget doesn't get quietly revised after the fact.",
    },
    {
        n: 2,
        title: 'Recommendations — same-day, delivery-risk labeled',
        body: "We look for a cheaper window on the same calendar day for each block that can actually move — production that runs unmanned, not work tied to when someone's on shift — with the same duration, never suggesting a shift that risks a delivery date. Every recommendation shows its projected savings and a delivery-risk rating (low / medium / high) side by side, so you're weighing the trade-off, not taking it on faith.",
    },
    {
        n: 3,
        title: 'Verified savings — measured, not projected',
        body: 'Each month, we compare your actual metered cost against your locked baseline, scaled to that period. The difference is verified savings — a backward-looking, measured number, not a forecast. If a month goes the wrong way (a bad price spell, a schedule change outside your control), verified savings can come out lower than expected, or zero — never negative into a charge.',
    },
    {
        n: 4,
        title: "Shadow review — two internal checks before it's billed",
        body: "A newly computed verification record doesn't go straight to your invoice. It's reviewed and confirmed internally, then approved a second time, before it's ever used to bill you. New pilots run in shadow mode — verification runs and is reviewed, but nothing is billed on it — for the first full billing cycle or two, so we're both confident in the number before it has financial weight.",
    },
];

export default function Methodology() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Methodology" />
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 pt-16 pb-4 text-center">
                    <div className="mx-auto max-w-[860px]">
                        <Eyebrow>Methodology</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            A number you can defend to your own auditor.
                        </h1>
                        <p className="text-ink-500 mx-auto mt-5 max-w-2xl text-lg">
                            Four stages, each one measured against data you
                            already have — your own meters and published
                            day-ahead prices — so a savings figure can always be
                            traced back to its calculation, not taken on trust.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-14">
                    <div className="mx-auto max-w-[1000px]">
                        <div className="grid gap-6 sm:grid-cols-2">
                            {STAGES.map((s) => (
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

                <section className="border-line-100 bg-surface-0 border-y px-6 py-16">
                    <div className="mx-auto max-w-[800px]">
                        <Eyebrow>Where we are today</Eyebrow>
                        <h2 className="font-display text-navy-900 text-2xl font-semibold sm:text-3xl">
                            We call our own methodology
                            &ldquo;provisional,&rdquo; on purpose.
                        </h2>
                        <p className="text-ink-500 mt-4 text-sm">
                            Every baseline, recommendation, and verification we
                            generate today is labeled internally as a
                            provisional methodology, with an explicit version
                            number. That&apos;s not a hedge — it&apos;s the same
                            rigor we&apos;d want from a supplier of our own: as
                            our models improve (more price-data history, more
                            nuanced production constraints, cross-checks against
                            a second market data source), the version changes
                            and the change is visible, rather than a silent
                            revision to how &ldquo;savings&rdquo; is defined
                            underneath you.
                        </p>
                        <p className="text-ink-500 mt-3 text-sm">
                            Every savings assessment and invoice states which
                            methodology version produced its numbers, so a
                            figure from month one and a figure from month twelve
                            are always comparable — or clearly flagged as not,
                            if the methodology changed in between.
                        </p>
                    </div>
                </section>

                <section className="px-6 py-14 text-center">
                    <div className="mx-auto max-w-[700px]">
                        <h2 className="font-display text-navy-900 text-xl font-semibold">
                            What this methodology deliberately doesn&apos;t do —
                            yet
                        </h2>
                        <p className="text-ink-500 mt-3 text-sm">
                            It doesn&apos;t model multi-day or cross-week
                            shifts, only same-day windows — the lower-risk
                            starting point while we&apos;re proving the model
                            out with early design partners. It doesn&apos;t yet
                            cross-check ENTSO-E price data against a second
                            source. Both are real roadmap items, prioritized by
                            what our first pilots actually need, not built ahead
                            of that evidence.
                        </p>
                    </div>
                </section>

                <section className="bg-navy-900 px-6 py-16 text-center text-white">
                    <h2 className="font-display text-2xl font-semibold text-white sm:text-3xl">
                        See what your schedule is worth.
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-white/70">
                        Applications are reviewed individually. Tell us about
                        your process and we&apos;ll follow up with a
                        qualification call.
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
                            href={route('pricing')}
                            className={cn(
                                buttonVariants({
                                    variant: 'secondary',
                                    size: 'lg',
                                }),
                                'border-white/30 bg-transparent text-white hover:bg-white/10',
                            )}
                        >
                            See pricing
                        </Link>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
