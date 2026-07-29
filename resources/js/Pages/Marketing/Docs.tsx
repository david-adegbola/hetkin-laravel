import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { Head, Link } from '@inertiajs/react';

/**
 * Customer-facing documentation — the longer-form counterpart to Beta.tsx's
 * quick-start guide. Distinct from Admin/Docs.tsx, which is the internal
 * staff handbook; this one is public-reachable (if unlinked from nav) and
 * `noindex`, same rationale as Beta.tsx.
 */
const CONTACT_EMAIL = 'hello@hetkin.app';

const PAGES = [
    {
        name: 'Dashboard',
        body: "Your home screen when you sign in. A checklist walks a brand-new account through the first few things to do: upload consumption data, generate a savings assessment, add a schedule, act on a recommendation. Below the checklist you'll find summary stats (current savings, active recommendations, open alerts) and a feed of recent activity. On a new account these all show zero or empty — that's the expected starting state, not a bug.",
    },
    {
        name: 'Schedule',
        body: 'Where production runs are entered — one block at a time, by hand. Each block needs an asset or line name, a start time, an end time, and an estimated energy use in kWh. That kWh figure is what makes a block eligible for optimization at all; a block without one is recorded but never considered for a recommendation. Once you\'ve entered a few blocks, "Run optimization" checks each one for a cheaper same-day window. Past schedule versions are kept and shown read-only — nothing is ever silently overwritten.',
    },
    {
        name: 'Data',
        body: 'Electricity consumption data is uploaded here as a CSV, in the Fingrid Datahub export format, at either hourly or 15-minute resolution. This is the raw input every savings calculation is built from — a baseline, a recommendation, and a verified-savings figure are all only as good as the consumption data behind them. There is currently no automated or live connection to a meter data platform; every upload is a manual file.',
    },
    {
        name: 'Reports',
        body: 'Once a savings assessment has been generated, its full report lives here: the baseline calculation, the price analysis behind it, the recommendations produced, the projected savings, and the methodology version used to produce all of it. Reports can be viewed in the portal or downloaded.',
    },
    {
        name: 'Billing',
        body: 'Pricing terms and invoices, once they exist. Nothing appears here until a baseline has been locked and at least one full monthly verification cycle has completed — invoicing follows measured, verified savings, never a projection, so a brand-new account or a short test window will typically show nothing here yet.',
    },
    {
        name: 'Team & Permissions',
        body: 'Shows everyone on the organization and their role — Owner, Scheduler, Finance, or Viewer — each with a different level of access to schedules, data, and billing.',
    },
    {
        name: 'Support',
        body: 'Contact information for reaching the Hetkin team directly.',
    },
];

const GLOSSARY = [
    {
        term: 'Baseline',
        body: "A documented estimate of what a customer's electricity cost would have been without Hetkin's recommendations, built from historical meter data and production patterns, and locked before comparisons begin.",
    },
    {
        term: 'Savings Assessment',
        body: 'A report comparing current cost against an optimized schedule, showing projected savings and exactly how that estimate was calculated.',
    },
    {
        term: 'Recommendation',
        body: 'A suggestion to shift a specific, flexible production block to a cheaper same-day window, shown with its projected savings and a delivery-risk rating.',
    },
    {
        term: 'Recommendation Decision',
        body: 'The record of what a customer chose to do with a recommendation — accept, reject, or partially adopt.',
    },
    {
        term: 'Verified Savings',
        body: 'A backward-looking, measured figure: actual metered cost compared against the locked baseline for that period. Unlike a projection, it can come out lower than expected, or zero — never negative into a charge.',
    },
    {
        term: 'Provisional / Estimated',
        body: 'The label attached to every savings figure produced by the current methodology version, signaling that the methodology itself may still be refined — not a hedge on any individual calculation.',
    },
    {
        term: 'Metering Point',
        body: "A specific electricity meter tied to a customer's site, identified by its Fingrid Datahub metering point code.",
    },
    {
        term: 'Day-ahead price',
        body: 'The hourly electricity price published a day in advance by Nord Pool for the Finnish bidding zone — the price series every recommendation and verification is measured against.',
    },
];

export default function Docs() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Documentation">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 py-16">
                    <div className="mx-auto max-w-[820px] text-center">
                        <Eyebrow>Documentation</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            How the Hetkin portal works
                        </h1>
                        <p className="text-ink-500 mx-auto mt-4 max-w-[620px] text-sm">
                            A page-by-page reference for everything in the
                            portal, plus a glossary of the terms you&rsquo;ll
                            see on screen. Looking for a quick-start instead?
                            See the{' '}
                            <Link
                                href={route('beta')}
                                className="hover:text-navy-900 underline"
                            >
                                beta testing guide
                            </Link>
                            .
                        </p>
                    </div>

                    <div className="mx-auto mt-14 max-w-[820px] space-y-10">
                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                Pages
                            </h2>
                            <div className="mt-4 space-y-4">
                                {PAGES.map((p) => (
                                    <div
                                        key={p.name}
                                        className="border-line-100 bg-surface-0 rounded-card border p-4"
                                    >
                                        <h3 className="font-display text-navy-900 text-sm font-semibold">
                                            {p.name}
                                        </h3>
                                        <p className="text-ink-500 mt-1 text-sm">
                                            {p.body}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                Glossary
                            </h2>
                            <dl className="border-line-100 bg-surface-0 rounded-card mt-4 divide-y border">
                                {GLOSSARY.map((g) => (
                                    <div key={g.term} className="p-4">
                                        <dt className="font-display text-navy-900 text-sm font-semibold">
                                            {g.term}
                                        </dt>
                                        <dd className="text-ink-500 mt-1 text-sm">
                                            {g.body}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>

                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                Still stuck?
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                Message us directly at{' '}
                                <a
                                    href={`mailto:${CONTACT_EMAIL}`}
                                    className="hover:text-navy-900 underline"
                                >
                                    {CONTACT_EMAIL}
                                </a>
                                .
                            </p>
                        </div>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
