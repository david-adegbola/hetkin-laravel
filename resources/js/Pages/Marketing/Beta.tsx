import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { Head, Link } from '@inertiajs/react';

/**
 * Beta-tester welcome page — deliberately not linked from SiteHeader's nav
 * or the homepage (there is no self-serve pilot signup); this page is the
 * pre-signup equivalent, sent directly to invited testers rather than
 * discovered. `noindex` keeps it out of search results too —
 * "unlisted", not password-protected, matching the sensitivity level of
 * what's actually on it (no credentials, no customer data).
 *
 * Content is the customer-facing counterpart to the admin staff guide
 * (Admin/Docs.tsx) — this one's source of truth is v2's
 * BETA_TESTING_GUIDE.md; keep the two in sync if either changes rather
 * than letting them drift apart.
 */
// Hardcoded, not config('mail.pilot_notification_address') — that config
// value is the internal notification recipient for new pilot applications
// and may be set to a personal address. This page is customer-visible, so
// it follows the same convention as every other public page (Trust,
// Legal/*): the address is hardcoded directly, never sourced from that
// config value.
const CONTACT_EMAIL = 'hello@hetkin.app';

const PORTAL_SECTIONS = [
    {
        name: 'Dashboard',
        body: "Your home screen. A checklist walks you through the first few things to do — upload data, generate an assessment, add a schedule, act on a recommendation. Below that: savings stats, recent recommendations, and alerts. Everything shows zero or empty at first — that's expected on a brand-new account, not a bug.",
    },
    {
        name: 'Schedule',
        body: 'Where you enter production runs. Fill in an asset or line name, start and end time, estimated energy use in kWh, and whether the activity can move without changing staffed production. Only reschedulable activities with a kWh figure are considered for timing recommendations. Add a few blocks, then click “Run optimization” to see if a lower-cost time window exists.',
    },
    {
        name: 'Data',
        body: "Upload electricity consumption data as a CSV (Fingrid Datahub export format, hourly or 15-minute resolution). This is what feeds every savings calculation. If you don't have a real export handy, ask us for a sample file — we can provide one shaped for testing.",
    },
    {
        name: 'Reports',
        body: "Once you've generated a savings assessment, view or download the decision report here — estimated energy-price savings, recommended timing changes and what to confirm before acting.",
    },
    {
        name: 'Billing',
        body: 'Shows pricing terms and invoices, once any exist. During a short test window this will likely stay empty — invoicing only happens after a baseline is locked and a full monthly verification cycle runs, which is a longer process than one testing session.',
    },
    {
        name: 'Team & Permissions',
        body: "Shows who's on your team and their role (Owner, Scheduler, Finance, Viewer).",
    },
    {
        name: 'Support',
        body: 'Contact info for reaching us directly.',
    },
];

const TEST_CHECKLIST = [
    'Go through the full flow once: accept the invite → upload a data CSV → add a few schedule blocks with different times and kWh values → run optimization → generate a savings assessment → look at the report.',
    'Try weird inputs: empty fields, huge numbers, past dates, overlapping schedule blocks, a CSV in the wrong format.',
    'Try it on your phone, not just a laptop.',
    'Use the back button, refresh mid-form, open two tabs — anything a real person might accidentally do.',
    'Read the actual words on the screen — do labels, buttons, and error messages make sense, or would you be confused if this were your actual job?',
];

const KNOWN_LIMITATIONS = [
    'Recommendations only ever shift a block within the same day, and only appear if the cheaper option is at least 5% cheaper and saves at least €1 — small or multi-day cases won’t produce anything, on purpose.',
    'Every savings figure is clearly labeled as an estimate until it has been verified against a completed billing period.',
    'No bulk import — schedule blocks are entered one at a time by hand for now.',
    'No live Fingrid connection — CSV upload is the only way to get data in right now.',
    "The Team page shows roles, but there's no in-app way to invite a new teammate yourself — that's still done by us directly.",
    'The Support page is just contact info — no chat or ticket system yet.',
    "Schedule history is read-only on purpose — old versions can't be edited or deleted.",
];

export default function Beta() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Beta Testing Guide">
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 py-16">
                    <div className="mx-auto max-w-[820px] text-center">
                        <Eyebrow>Beta Testing</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            You&rsquo;re testing Hetkin before anyone else does
                        </h1>
                        <p className="text-ink-500 mx-auto mt-4 max-w-[620px] text-sm">
                            Thanks for agreeing to poke around before this goes
                            out to real pilot customers. You don&rsquo;t need
                            any background on the product — this page walks you
                            through everything. Hetkin helps manufacturing
                            companies save money by shifting production to
                            cheaper electricity hours; you&rsquo;ll be testing
                            it as if you were a factory manager or scheduler at
                            a customer company.
                        </p>
                        <p className="text-ink-500 mx-auto mt-3 max-w-[620px] text-sm">
                            We&rsquo;re still pre-launch, so a few things are
                            unfinished on purpose (listed below) — you
                            don&rsquo;t need to report those, just everything
                            else that feels broken, confusing, or wrong.
                        </p>
                    </div>

                    <div className="mx-auto mt-14 max-w-[820px] space-y-10">
                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                1. Getting in
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                You&rsquo;ll get an email invite (&ldquo;Your
                                Hetkin pilot is approved &mdash; set up your
                                account&rdquo;) with a link. If it doesn&rsquo;t
                                arrive within a few minutes, just ask us
                                directly and we&rsquo;ll send the link manually
                                — no need to wait around.
                            </p>
                            <p className="text-ink-500 mt-2 text-sm">
                                Click the link and you&rsquo;ll land on a page
                                to join your organization: your email is
                                pre-filled, so just enter your name and choose a
                                password (12 characters minimum). Hit
                                &ldquo;Create account&rdquo; and you&rsquo;re
                                in.
                            </p>
                            <p className="text-ink-500 mt-2 text-sm">
                                There&rsquo;s no public sign-up page — this
                                invite link is the only way in, so don&rsquo;t
                                lose it (it expires after 14 days). Coming back
                                later? Sign in at{' '}
                                <code className="bg-surface-100 rounded px-1.5 py-0.5 text-xs">
                                    /sign-in
                                </code>{' '}
                                with the email and password you set.
                            </p>
                        </div>

                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                2. What you&rsquo;ll see
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                Once you&rsquo;re in, the sidebar has:
                                Dashboard, Schedule, Data, Reports, Billing,
                                Team &amp; Permissions, Support.
                            </p>
                            <div className="mt-4 space-y-4">
                                {PORTAL_SECTIONS.map((s) => (
                                    <div
                                        key={s.name}
                                        className="border-line-100 bg-surface-0 rounded-card border p-4"
                                    >
                                        <h3 className="font-display text-navy-900 text-sm font-semibold">
                                            {s.name}
                                        </h3>
                                        <p className="text-ink-500 mt-1 text-sm">
                                            {s.body}
                                        </p>
                                    </div>
                                ))}
                            </div>
                            <p className="text-ink-500 mt-4 text-sm">
                                Want more detail on any of these than fits here?
                                The{' '}
                                <Link
                                    href={route('docs')}
                                    className="hover:text-navy-900 underline"
                                >
                                    full documentation
                                </Link>{' '}
                                goes deeper on how everything actually works,
                                including a glossary of terms you&rsquo;ll see
                                on screen.
                            </p>
                        </div>

                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                3. What to actually test
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                Please try to break things, not just click
                                through happily. Specifically:
                            </p>
                            <ul className="text-ink-500 mt-3 list-disc space-y-2 pl-5 text-sm">
                                {TEST_CHECKLIST.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </div>

                        <div className="border-line-100 bg-surface-0 rounded-card border p-6">
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                4. Known limitations
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                Not bugs — you don&rsquo;t need to report any of
                                these, we already know:
                            </p>
                            <ul className="text-ink-500 mt-3 list-disc space-y-2 pl-5 text-sm">
                                {KNOWN_LIMITATIONS.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        </div>

                        <div>
                            <h2 className="font-display text-navy-900 text-xl font-semibold">
                                5. How to send feedback
                            </h2>
                            <p className="text-ink-500 mt-2 text-sm">
                                For anything else — bugs, confusing wording,
                                things that felt slow or unclear, or ideas —
                                message us directly at{' '}
                                <a
                                    href={`mailto:${CONTACT_EMAIL}`}
                                    className="hover:text-navy-900 underline"
                                >
                                    {CONTACT_EMAIL}
                                </a>
                                . When something breaks, it helps a lot if you
                                include: what page you were on, what you did,
                                what you expected, and what happened instead — a
                                screenshot is even better.
                            </p>
                            <p className="font-display text-navy-900 mt-4 text-sm font-medium">
                                Thanks again for doing this before it goes
                                anywhere near a real customer.
                            </p>
                        </div>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
