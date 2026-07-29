import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head } from '@inertiajs/react';
import { ReactElement, ReactNode } from 'react';

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/docs/page.tsx — the
 * in-app staff guide, ported content-for-content. Low priority per the
 * Stage 9 research pass (a static page, not a data-backed console screen),
 * but the content itself is real, substantive documentation in v2, not a
 * placeholder — so it's ported in full rather than stubbed.
 */
const ROLES: Array<{ role: string; body: string }> = [
    {
        role: 'ADMIN',
        body: 'Everything — including pricing overrides, invoicing, staff management, and anything below.',
    },
    {
        role: 'VERIFICATION_ANALYST',
        body: 'Lock baselines, review and approve/dispute verification records.',
    },
    {
        role: 'SUPPORT',
        body: 'Review and approve/reject pilot applications, manage members and invitations, leave internal notes.',
    },
    {
        role: 'AUDITOR',
        body: "Read-only, everywhere. Can see everything above but can't submit any action.",
    },
];

const WALKTHROUGH: Array<{ id: string; title: string; body: ReactNode }> = [
    {
        id: 'overview',
        title: 'Overview',
        body: (
            <>
                <p>
                    Your &ldquo;is anything on fire&rdquo; screen — nothing here
                    is editable, it&rsquo;s all live counts pulled straight from
                    the database.
                </p>
                <ul className="list-disc space-y-1.5 pl-5">
                    <li>
                        <strong>Pipeline</strong> — pilot applications waiting
                        on review, organization counts by status, how many
                        baselines are locked vs. still draft.
                    </li>
                    <li>
                        <strong>Verification &amp; billing</strong> —
                        verification records awaiting your review, anything
                        flagged for an unusual variance, invoices sitting in
                        draft, and total outstanding (issued but unpaid).
                    </li>
                    <li>
                        <strong>Platform</strong> — how fresh the electricity
                        price data is, whether the background jobs are healthy,
                        whether the audit log&rsquo;s integrity check passes,
                        and how many active admin accounts exist.
                    </li>
                </ul>
                <p>
                    If anything needs attention (stale price data, a failed job,
                    a broken audit chain, or a verification record waiting on
                    first review), a red &ldquo;Needs attention&rdquo; box
                    appears at the top telling you exactly what and pointing you
                    to the right page.
                </p>
            </>
        ),
    },
    {
        id: 'pilots',
        title: 'Pilots',
        body: (
            <>
                <p>
                    Every prospective customer&rsquo;s application, in one
                    queue, sorted by fit — not submission date.
                </p>
                <p>
                    Each pending application shows the company name, what kind
                    of production process they run, their estimated annual
                    electricity spend, and an <strong>ICP score</strong> (see
                    Glossary) as a colored badge — green (70+) is a strong fit,
                    amber (40&ndash;69) is worth a look, red (under 40) is a
                    stretch. You have two buttons: Approve or Reject.
                </p>
                <p>
                    Approving does a few things automatically: it creates the
                    customer&rsquo;s Organization record, and emails an invite
                    link to the contact so they can set up their account. If
                    email delivery isn&rsquo;t working, the invite link is also
                    shown directly on this page under &ldquo;Reviewed,&rdquo; in
                    a copyable field, so you can send it yourself.
                </p>
            </>
        ),
    },
    {
        id: 'organizations',
        title: 'Organizations',
        body: (
            <>
                <p>
                    The central hub for everything about one signed customer —
                    click into any organization from the list. Think of it as
                    that customer&rsquo;s full file:
                </p>
                <ul className="list-disc space-y-1.5 pl-5">
                    <li>
                        <strong>Status</strong> — Pilot / Active / Paused /
                        Churned, editable here.
                    </li>
                    <li>
                        <strong>Commercial terms</strong> — base fee and
                        revenue-share percentage, set per customer (not a
                        published rate card).
                    </li>
                    <li>
                        <strong>Members</strong> — everyone with an account at
                        this organization and their role, with the ability to
                        change a role or remove someone.
                    </li>
                    <li>
                        <strong>Pending invitations</strong> — invites sent but
                        not yet accepted, with a resend option.
                    </li>
                    <li>
                        <strong>Baselines</strong>,{' '}
                        <strong>Verification records</strong>, and{' '}
                        <strong>Invoices</strong> — a read-only history for this
                        customer.
                    </li>
                    <li>
                        <strong>Internal notes</strong> — context for your team
                        (General / Support / Risk / Billing) that customers
                        never see.
                    </li>
                    <li>
                        <strong>Activity</strong> — a timeline built entirely
                        from the audit log.
                    </li>
                    <li>
                        <strong>Data erasure (GDPR)</strong> — a deliberately
                        separate, confirmation-gated action (type the
                        org&rsquo;s slug to confirm) that anonymizes a
                        member&rsquo;s personal contact details on request.
                        Never touches billing or verification history.
                        ADMIN-only.
                    </li>
                </ul>
            </>
        ),
    },
    {
        id: 'baselines',
        title: 'Baselines',
        body: (
            <>
                <p>
                    A queue of every baseline that&rsquo;s been generated
                    (automatically, whenever a customer generates their first
                    savings assessment) and needs a decision: lock it, or leave
                    it as draft.
                </p>
                <p className="text-primary font-medium">
                    Locking is the one deliberate, one-way action in this whole
                    product.
                </p>
                <p>
                    Once you lock a baseline, it becomes the permanent,
                    unmovable reference point every future month&rsquo;s
                    verified savings gets measured against for that customer —
                    it can&rsquo;t be quietly adjusted later. Don&rsquo;t lock
                    one you haven&rsquo;t actually looked at.
                </p>
            </>
        ),
    },
    {
        id: 'verification',
        title: 'Verification',
        body: (
            <>
                <p>
                    Every month, a background job compares a customer&rsquo;s
                    actual metered cost against their locked baseline and
                    produces a Verification Record — the number that eventually
                    becomes a real invoice line. Every new record starts in
                    shadow review and needs two separate approvals before
                    it&rsquo;s trusted enough to bill:
                </p>
                <p className="rounded-control bg-surface border-border border px-3 py-2 font-mono text-xs">
                    SHADOW_PENDING_REVIEW → Confirm → SHADOW_CONFIRMED → Approve
                    (live) → LIVE_APPROVED
                </p>
                <p>
                    At either shadow stage you can instead click Dispute, which
                    sends it to DISPUTED for a full re-review rather than
                    letting a questionable number quietly proceed. Records with
                    an unusually large variance are flagged automatically (shown
                    in red) so they get extra scrutiny before you approve them,
                    not after.
                </p>
                <p>
                    Nothing in the system can skip this two-step gate —
                    there&rsquo;s no &ldquo;approve and bill in one click&rdquo;
                    shortcut, on purpose.
                </p>
            </>
        ),
    },
    {
        id: 'invoices',
        title: 'Invoices',
        body: (
            <>
                <p>Two lists on one page:</p>
                <ul className="list-disc space-y-1.5 pl-5">
                    <li>
                        <strong>Ready to invoice</strong> — every LIVE_APPROVED
                        verification record without an invoice yet. Generate
                        invoice creates the invoice record (base fee + their
                        share of verified savings) — it does not charge a card
                        or notify the customer; there&rsquo;s no payment
                        processor wired up yet.
                    </li>
                    <li>
                        <strong>All invoices</strong> — the full history, with
                        status-specific actions: Issue or Void a draft, Mark
                        paid (with an optional payment note) or Void an issued
                        one. Issuing is a separate step from generating, and
                        marking paid is a manual record-keeping action —
                        there&rsquo;s no automatic payment detection.
                    </li>
                </ul>
                <p>
                    If a period&rsquo;s verified savings come out at zero, the
                    invoice still generates — just with the revenue-share line
                    at zero. The base fee is the only thing ever charged
                    regardless of outcome, and nothing is ever billed as a
                    negative number.
                </p>
            </>
        ),
    },
    {
        id: 'jobs',
        title: 'Jobs',
        body: (
            <>
                <p>
                    Background jobs run on a schedule and can also be triggered
                    manually with a Run now button. Only &ldquo;Run monthly
                    verification&rdquo; is wired up to a manual trigger in this
                    deployment today — it computes the previous month&rsquo;s
                    Verification Records for every customer with a locked
                    baseline.
                </p>
                <p>
                    A run history table shows every past execution, whether it
                    succeeded or failed, how long it took, and whether it was
                    triggered by the schedule or by a person.
                </p>
            </>
        ),
    },
    {
        id: 'audit-log',
        title: 'Audit Log',
        body: (
            <>
                <p>
                    A single, filterable timeline of every meaningful action
                    taken anywhere in the platform — pilot approvals, baseline
                    locks, verification approvals, invoice changes, role
                    changes, and more. Filter by organization, actor type,
                    entity type, action text, or a date range.
                </p>
                <p>
                    The log is hash-chained — each entry&rsquo;s fingerprint
                    includes the previous entry&rsquo;s fingerprint, so if a row
                    were ever edited or deleted outside the normal application
                    flow, the chain would visibly break. The integrity check
                    runs automatically on every page load (also surfaced on
                    Overview if it ever fails).
                </p>
            </>
        ),
    },
    {
        id: 'team',
        title: 'Team',
        body: (
            <>
                <p>
                    Manage other admin accounts. Only the ADMIN role can create
                    new accounts, change anyone&rsquo;s role, or
                    deactivate/reactivate someone — everyone else sees this page
                    read-only.
                </p>
                <p>
                    Accounts are deactivated, never deleted — this keeps every
                    past action in the audit log correctly attributed to a real
                    name, even for someone who&rsquo;s left the team.
                </p>
            </>
        ),
    },
];

const LIFECYCLE = [
    'A prospective customer submits a pilot application → shows up in Pilots, scored automatically.',
    'You approve it → an Organization is created and an invite is emailed (or copied manually).',
    'The customer accepts the invite, sets a password, and is now signed into their own portal.',
    'They upload their electricity consumption data and generate a savings assessment → a Baseline (draft) is created automatically.',
    'You review and lock that baseline in Baselines — this is the point of no return for what "no Hetkin" would have cost them.',
    'The customer adds their production schedule and runs optimization, then accepts/adjusts/rejects the recommendations they get.',
    'Every month, a background job computes a Verification Record comparing their actual cost to the locked baseline.',
    "You shadow-review that record in Verification — two approvals — before it's trusted.",
    'Once LIVE_APPROVED, you generate and issue an invoice in Invoices, then mark it paid once payment actually arrives (manually, for now).',
];

const GLOSSARY: Array<{ term: string; body: string }> = [
    {
        term: 'Pilot Application',
        body: 'The form a prospective customer fills out to start a relationship with Hetkin. Reviewed and either approved (creating a real customer Organization) or rejected.',
    },
    {
        term: 'ICP score',
        body: '"Ideal Customer Profile" — an automatically computed fit score based on how flexible the company\'s production process is and how large their electricity spend is. Higher is a better fit.',
    },
    {
        term: 'Organization',
        body: 'The customer account/tenant itself. Created automatically the moment a pilot application is approved. Status: PILOT, ACTIVE, PAUSED, or CHURNED.',
    },
    {
        term: 'Member',
        body: "A person who belongs to a customer's Organization, with a role: OWNER, SCHEDULER, FINANCE, or VIEWER. Different from an admin account — members are customer-side.",
    },
    {
        term: 'Base fee / Revenue share',
        body: "Hetkin's pricing has two parts: a small recurring platform fee, plus a percentage of whatever savings were actually verified that period. Negotiated per customer, not a published rate.",
    },
    {
        term: 'Baseline',
        body: "A fixed estimate, agreed before real billing starts, of what a customer's electricity cost would have been without Hetkin. Starts as DRAFT, becomes LOCKED only when an admin deliberately locks it.",
    },
    {
        term: 'Verification Record',
        body: 'The backward-looking, monthly comparison of what a customer actually paid against their locked baseline. Goes through SHADOW_PENDING_REVIEW → SHADOW_CONFIRMED → LIVE_APPROVED (or DISPUTED).',
    },
    {
        term: 'Shadow review / shadow mode',
        body: "The two-approval internal review gate every Verification Record must pass through before it's trusted enough to invoice.",
    },
    {
        term: 'Variance flag',
        body: 'Automatic flag on a Verification Record whose result differs meaningfully from what was originally projected, so it gets closer scrutiny before approval.',
    },
    {
        term: 'Invoice',
        body: 'Generated from a LIVE_APPROVED Verification Record. Status flow: DRAFT → ISSUED → PAID, with Void available as an alternative at either of the first two stages.',
    },
    {
        term: 'Provisional',
        body: "A label attached to nearly every number this platform produces. The underlying methodology hasn't yet been through formal energy-market legal review.",
    },
    {
        term: 'Audit Log / audit chain',
        body: 'An append-only, hash-chained record of every meaningful action taken in the platform. Tampering would visibly break the chain rather than going unnoticed.',
    },
    {
        term: 'Job Run',
        body: 'A record of one execution of a background job, whether triggered by the schedule or by a person clicking "Run now."',
    },
    {
        term: 'GDPR erasure',
        body: "The manual, ADMIN-only action that anonymizes a member's personal/contact details on request. Never touches financial or operational records.",
    },
];

const LIMITATIONS = [
    'No payment processor is connected. Generating and issuing an invoice never charges a card — "Mark paid" is a manual record-keeping step once payment has actually arrived by whatever means (bank transfer today).',
    "No live meter-data connection. Customers upload a CSV export themselves; there's no automatic pull from their utility yet.",
    "Electricity price ingestion and data-gap alerts aren't wired up to automatic or manual triggers yet — only monthly verification is.",
    'Every figure is labeled "Estimated" or "Provisional" until the underlying methodology has been through formal legal review — this is intentional caution, not a placeholder that was forgotten.',
];

const TOC = [
    { id: 'getting-in', label: 'Getting in' },
    { id: 'two-sign-ins', label: 'Two separate sign-ins' },
    { id: 'roles', label: 'Your role' },
    ...WALKTHROUGH.map((w) => ({ id: w.id, label: w.title })),
    { id: 'lifecycle', label: 'Full lifecycle' },
    { id: 'glossary', label: 'Glossary' },
    { id: 'limitations', label: 'Not built yet' },
];

export default function Docs() {
    return (
        <div className="mx-auto max-w-[900px]">
            <Head title="Staff guide" />
            <div className="mb-6">
                <h1 className="font-display text-primary text-xl font-semibold">
                    Staff guide
                </h1>
                <p className="text-text-secondary text-sm">
                    A plain-language walkthrough of this admin console for
                    anyone on the team who uses it day to day. Doesn&rsquo;t
                    assume any coding background.
                </p>
            </div>

            <div className="rounded-card border-border bg-surface mb-8 flex flex-wrap gap-x-4 gap-y-1.5 border p-4 text-xs">
                {TOC.map((t) => (
                    <a
                        key={t.id}
                        href={`#${t.id}`}
                        className="text-text-secondary hover:text-primary underline"
                    >
                        {t.label}
                    </a>
                ))}
            </div>

            <div className="text-text-secondary space-y-10 text-sm [&_p]:leading-relaxed">
                <section id="getting-in">
                    <h2 className="font-display text-primary mb-2 text-lg font-semibold">
                        Getting in
                    </h2>
                    <p>
                        Sign in with the email and password you were given.
                        There&rsquo;s no self-serve sign-up — every admin
                        account is created by another admin from the Team page.
                        If you don&rsquo;t have an account yet, ask whoever
                        manages the team.
                    </p>
                    <p className="mt-2">
                        Signing in successfully lands you on Pilots, not
                        Overview — that&rsquo;s deliberate, since reviewing
                        pending applications is usually the first thing that
                        actually needs doing.
                    </p>
                </section>

                <section id="two-sign-ins">
                    <h2 className="font-display text-primary mb-2 text-lg font-semibold">
                        Two separate sign-ins
                    </h2>
                    <p>
                        The admin console and the customer portal are two
                        completely independent systems under the hood —
                        different sign-in pages, different session cookies,
                        different underlying accounts. Signing into one never
                        signs you into the other, and a customer account can
                        never be used to sign into the admin console (or vice
                        versa).
                    </p>
                    <p className="mt-2">
                        Practically: if a customer emails you saying they
                        can&rsquo;t sign in, that&rsquo;s their portal account,
                        not yours — you can&rsquo;t &ldquo;log in as them&rdquo;
                        from here (Organizations gets you the closest
                        equivalent, viewing their account state read-only).
                    </p>
                </section>

                <section id="roles">
                    <h2 className="font-display text-primary mb-2 text-lg font-semibold">
                        Your role and what it lets you do
                    </h2>
                    <p>
                        Every admin account has exactly one role. Roles are
                        enforced on the server, not just hidden in the UI —
                        you&rsquo;ll see a &ldquo;not authorized&rdquo; failure
                        if you try an action your role doesn&rsquo;t cover, not
                        a silently broken button.
                    </p>
                    <div className="rounded-card border-border bg-surface mt-3 overflow-x-auto border">
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="px-4 py-3">Role</th>
                                    <th className="px-4 py-3">Can do</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ROLES.map((r) => (
                                    <tr
                                        key={r.role}
                                        className="border-border border-b last:border-0"
                                    >
                                        <td className="text-primary px-4 py-3 font-mono text-xs font-semibold">
                                            {r.role}
                                        </td>
                                        <td className="px-4 py-3">{r.body}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section>
                    <h2 className="font-display text-primary mb-4 text-lg font-semibold">
                        Walkthrough, section by section
                    </h2>
                    <p className="mb-4">
                        The left-to-right order of the top nav is deliberate —
                        it roughly follows a customer&rsquo;s lifecycle from
                        &ldquo;applied&rdquo; to &ldquo;being billed,&rdquo;
                        except for Jobs/Audit Log/Team, which are
                        platform-operations pages.
                    </p>
                    <div className="space-y-6">
                        {WALKTHROUGH.map((w) => (
                            <div
                                key={w.id}
                                id={w.id}
                                className="rounded-card border-border bg-surface border p-5"
                            >
                                <h3 className="font-display text-primary mb-2 text-base font-semibold">
                                    {w.title}
                                </h3>
                                <div className="space-y-2">{w.body}</div>
                            </div>
                        ))}
                    </div>
                </section>

                <section id="lifecycle">
                    <h2 className="font-display text-primary mb-2 text-lg font-semibold">
                        The full lifecycle, start to finish
                    </h2>
                    <p className="mb-3">
                        If you&rsquo;re new, the fastest way to understand how
                        everything connects is to follow one customer through
                        the whole thing.
                    </p>
                    <ol className="list-decimal space-y-2 pl-5">
                        {LIFECYCLE.map((step, i) => (
                            <li key={i}>{step}</li>
                        ))}
                    </ol>
                    <p className="mt-3">
                        Everything that happened along the way is
                        reconstructable afterward from Audit Log and the
                        organization&rsquo;s own Activity timeline.
                    </p>
                </section>

                <section id="glossary">
                    <h2 className="font-display text-primary mb-2 text-lg font-semibold">
                        Glossary
                    </h2>
                    <dl className="mt-2 space-y-3">
                        {GLOSSARY.map((g) => (
                            <div key={g.term}>
                                <dt className="font-display text-primary text-sm font-semibold">
                                    {g.term}
                                </dt>
                                <dd className="mt-0.5">{g.body}</dd>
                            </div>
                        ))}
                    </dl>
                </section>

                <section
                    id="limitations"
                    className="rounded-card border-border bg-surface border p-5"
                >
                    <div className="mb-2 flex items-center gap-2">
                        <h2 className="font-display text-primary text-lg font-semibold">
                            Things that are deliberately not built yet
                        </h2>
                        <Badge variant="mid" showGlyph={false}>
                            Known
                        </Badge>
                    </div>
                    <p className="mb-3">
                        Worth knowing so you don&rsquo;t report these as bugs:
                    </p>
                    <ul className="list-disc space-y-2 pl-5">
                        {LIMITATIONS.map((l, i) => (
                            <li key={i}>{l}</li>
                        ))}
                    </ul>
                    <p className="mt-3">
                        If something else feels broken, confusing, or just
                        doesn&rsquo;t make sense, that&rsquo;s worth flagging
                        regardless — this list is only the specific, known,
                        deliberate gaps.
                    </p>
                </section>
            </div>
        </div>
    );
}

Docs.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;
