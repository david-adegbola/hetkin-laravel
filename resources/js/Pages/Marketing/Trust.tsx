import Eyebrow from '@/Components/marketing/Eyebrow';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { Head, Link } from '@inertiajs/react';

const ITEMS = [
    {
        title: 'Data isolation by organization',
        body: "Every customer organization's data is scoped at the database level, so one organization's schedules, consumption data, and reports are never visible to another.",
    },
    {
        title: 'Encrypted in transit',
        body: "All traffic to the Hetkin portal and website runs over HTTPS. Data is stored with our infrastructure providers' standard encryption at rest.",
    },
    {
        title: 'Auditable by design',
        body: 'Every recommendation, schedule decision, and admin action is written to a tamper-evident audit log, so a savings figure can always be traced back to how it was calculated.',
    },
    {
        title: 'Separate internal access',
        body: 'Hetkin staff use a completely separate login system from customer accounts, with shorter sessions and stronger password requirements, so a customer credential can never grant internal access.',
    },
];

export default function Trust() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Security" />
            <SiteHeader />
            <main className="flex-1 px-6 py-16">
                <div className="mx-auto max-w-[900px] text-center">
                    <Eyebrow>Security</Eyebrow>
                    <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                        How we protect your data
                    </h1>
                    <p className="text-ink-500 mx-auto mt-4 max-w-[600px] text-sm">
                        Hetkin is in an active pilot phase. Here&rsquo;s what
                        &rsquo;s actually true about how we handle your data
                        today — we&rsquo;d rather be precise than impressive.
                    </p>
                </div>
                <div className="mx-auto mt-12 grid max-w-[900px] gap-8 sm:grid-cols-2">
                    {ITEMS.map((item) => (
                        <div key={item.title}>
                            <h4 className="font-display text-navy-900 text-base font-semibold">
                                {item.title}
                            </h4>
                            <p className="text-ink-500 mt-1.5 text-sm">
                                {item.body}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="mx-auto mt-14 max-w-[700px] space-y-6">
                    <div className="border-line-100 bg-surface-0 rounded-card text-ink-500 border p-6 text-sm">
                        <h4 className="font-display text-navy-900 text-base font-semibold">
                            How your data gets into Hetkin today
                        </h4>
                        <p className="mt-2">
                            During the pilot, electricity consumption data is
                            uploaded by your team as a file — we don&rsquo;t yet
                            have an automated, direct integration with meter
                            data platforms such as Fingrid Datahub. That&rsquo;s
                            on our roadmap; when it ships, it&rsquo;ll be opt-in
                            and clearly explained here first.
                        </p>
                    </div>

                    <div className="border-line-100 bg-surface-0 rounded-card text-ink-500 border p-6 text-sm">
                        <h4 className="font-display text-navy-900 text-base font-semibold">
                            Where we are on formal compliance
                        </h4>
                        <p className="mt-2">
                            We build with GDPR principles in mind — data
                            minimization, defined retention periods, and support
                            for access and erasure requests (see our{' '}
                            <Link
                                href={route('legal.privacy')}
                                className="hover:text-navy-900 underline"
                            >
                                Privacy Policy
                            </Link>
                            ). We don&rsquo;t yet hold formal certifications
                            such as SOC 2 or ISO 27001, and we haven&rsquo;t
                            completed a third-party security audit. As we move
                            past the pilot stage, formal certification is
                            something we plan to pursue — we&rsquo;ll update
                            this page when it happens rather than claim it
                            early.
                        </p>
                    </div>

                    <div className="border-line-100 bg-surface-0 rounded-card text-ink-500 border p-6 text-sm">
                        <p className="mb-0">
                            Alongside data protection, the rules that most
                            directly govern this business are Finnish
                            energy-market regulation and the contract terms
                            defining how &ldquo;verified savings&rdquo; and
                            revenue share are calculated and disputed. See our{' '}
                            <Link
                                href={route('legal.terms')}
                                className="hover:text-navy-900 underline"
                            >
                                Terms of Service
                            </Link>{' '}
                            for the current documented basis, and our{' '}
                            <Link
                                href={route('methodology')}
                                className="hover:text-navy-900 underline"
                            >
                                Methodology
                            </Link>{' '}
                            page for how a savings figure is calculated and
                            reviewed before it&rsquo;s billed.
                        </p>
                    </div>

                    <p className="text-ink-400 text-center text-xs">
                        Found a security issue? Please tell us at{' '}
                        <a
                            href="mailto:security@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            security@hetkin.app
                        </a>
                        .
                    </p>
                </div>
            </main>
            <SiteFooter />
        </div>
    );
}
