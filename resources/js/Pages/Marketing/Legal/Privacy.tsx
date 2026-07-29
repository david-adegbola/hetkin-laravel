import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { Head, Link } from '@inertiajs/react';
import { type ReactNode } from 'react';

function H2({ children }: { children: ReactNode }) {
    return (
        <h2 className="font-display text-navy-900 mt-10 text-lg font-semibold">
            {children}
        </h2>
    );
}

function P({ children }: { children: ReactNode }) {
    return (
        <p className="text-ink-500 mt-3 text-sm leading-relaxed">{children}</p>
    );
}

function UL({ children }: { children: ReactNode }) {
    return (
        <ul className="text-ink-500 mt-3 list-disc space-y-2 pl-5 text-sm leading-relaxed">
            {children}
        </ul>
    );
}

export default function Privacy() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Privacy Policy" />
            <SiteHeader />
            <main className="flex-1 px-6 py-16">
                <div className="mx-auto max-w-[720px]">
                    <h1 className="font-display text-navy-900 text-3xl font-semibold">
                        Privacy Policy
                    </h1>
                    <p className="text-ink-500 mt-3 text-sm">
                        Effective date:{' '}
                        <span className="text-ink-900 font-medium">
                            [to be confirmed on publication]
                        </span>
                        . Hetkin is currently operating as a pilot-stage
                        company; some details below — our registered business ID
                        and address — are marked as pending while company
                        registration is completed.
                    </p>

                    <P>
                        This policy explains what personal data Hetkin collects,
                        why, and what rights you have over it. It covers the
                        Hetkin website, the customer portal, and our internal
                        admin tools. If you have questions, contact us at{' '}
                        <a
                            href="mailto:privacy@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            privacy@hetkin.app
                        </a>
                        .
                    </P>

                    <H2>Who we are</H2>
                    <P>
                        Hetkin Oy (&ldquo;Hetkin,&rdquo; &ldquo;we,&rdquo;
                        &ldquo;us&rdquo;) is the data controller for the
                        personal data described in this policy.
                    </P>
                    <UL>
                        <li>
                            Business ID (Y-tunnus): [pending — company
                            registration in progress]
                        </li>
                        <li>
                            Registered address: [pending — company registration
                            in progress]
                        </li>
                        <li>Contact: privacy@hetkin.app</li>
                    </UL>

                    <H2>What data we collect</H2>
                    <P>
                        We collect different data depending on how you interact
                        with Hetkin:
                    </P>
                    <UL>
                        <li>
                            <strong className="text-ink-900">
                                Pilot applicants.
                            </strong>{' '}
                            When you submit a pilot application, we collect your
                            company name, contact name, email, and phone number,
                            your role and process type, and the business
                            information you provide about your electricity use
                            (e.g. estimated annual spend, current scheduling
                            approach).
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Customer-portal users.
                            </strong>{' '}
                            If your organization is accepted into the pilot, the
                            people you invite to use Hetkin have an account with
                            a name and email address, and we record which
                            organization and role they belong to. We also keep
                            basic session information (IP address, browser)
                            needed to keep sign-in secure.
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Your organization&rsquo;s operational data.
                            </strong>{' '}
                            This includes electricity consumption data your team
                            uploads (currently by manual file upload — we do not
                            yet have an automated meter-data integration live),
                            the production schedules you enter, and the
                            recommendations Hetkin generates and how your team
                            responds to them. This data is about your facility
                            and operations, not about identifiable individuals,
                            except where a schedule or decision is attributed to
                            the team member who entered it.
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Admin users.
                            </strong>{' '}
                            Hetkin staff who operate the platform have their own
                            accounts (name, email, role), kept entirely separate
                            from customer accounts.
                        </li>
                    </UL>

                    <H2>How we use your data</H2>
                    <UL>
                        <li>To evaluate and respond to pilot applications.</li>
                        <li>
                            To provide the customer portal: authentication,
                            generating savings assessments and schedule
                            recommendations, and showing you the results.
                        </li>
                        <li>
                            To communicate with you about your account, your
                            pilot, and product updates.
                        </li>
                        <li>
                            To keep the service secure and investigate misuse.
                        </li>
                        <li>
                            To understand how the product is used, so we can
                            improve it (see our Cookie Policy for analytics).
                        </li>
                        <li>
                            To meet our legal and accounting obligations,
                            including Finnish bookkeeping law, which requires
                            retaining certain financial records regardless of
                            any deletion request.
                        </li>
                    </UL>

                    <H2>Legal basis</H2>
                    <P>Under the GDPR, we rely on the following legal bases:</P>
                    <UL>
                        <li>
                            <strong className="text-ink-900">
                                Contract necessity
                            </strong>{' '}
                            — for onboarded customers, to provide the service
                            you&rsquo;ve signed up for.
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Legitimate interest
                            </strong>{' '}
                            — for reviewing pilot applications, keeping the
                            platform secure, and basic product analytics.
                        </li>
                        <li>
                            <strong className="text-ink-900">Consent</strong> —
                            for non-essential cookies (see our Cookie Policy)
                            and any future data-access integration that requires
                            it.
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Legal obligation
                            </strong>{' '}
                            — for financial and accounting records we&rsquo;re
                            required to keep under Finnish law.
                        </li>
                    </UL>

                    <H2>Who we share it with</H2>
                    <P>
                        We do not sell your personal data. We use a small number
                        of service providers to operate Hetkin, each acting as a
                        data processor under contract:
                    </P>
                    <UL>
                        <li>
                            Database hosting (Neon, PostgreSQL, EU region) —
                            stores all application data.
                        </li>
                        <li>
                            Transactional email (Resend) — sends account and
                            pilot-related emails.
                        </li>
                        <li>
                            Error monitoring (Sentry) — helps us detect and fix
                            bugs.
                        </li>
                        <li>
                            Product analytics (PostHog, EU-hosted) — helps us
                            understand product usage. See our Cookie Policy.
                        </li>
                        <li>Hosting and infrastructure (Vercel).</li>
                    </UL>
                    <P>
                        Some providers may process data outside the European
                        Economic Area. Where that happens, we rely on their
                        standard contractual safeguards (such as EU Standard
                        Contractual Clauses) for the transfer.
                    </P>

                    <H2>Cookies</H2>
                    <P>
                        We use essential cookies to keep you signed in and the
                        product secure, and (where you consent) non-essential
                        cookies for analytics. See our{' '}
                        <Link
                            href={route('legal.cookies')}
                            className="hover:text-navy-900 underline"
                        >
                            Cookie Policy
                        </Link>{' '}
                        for details and how to manage your preferences.
                    </P>

                    <H2>How long we keep your data</H2>
                    <UL>
                        <li>
                            <strong className="text-ink-900">
                                Personal / contact data
                            </strong>{' '}
                            (accounts, pilot application contact details) is
                            kept for as long as you have an active relationship
                            with Hetkin, and can be deleted on request — see
                            &ldquo;Your rights&rdquo; below.
                        </li>
                        <li>
                            <strong className="text-ink-900">
                                Operational and financial records
                            </strong>{' '}
                            (schedules, meter data, savings assessments,
                            invoices) are retained for the period required by
                            Finnish accounting law (currently six years), even
                            after a deletion request, because we&rsquo;re
                            legally required to keep them.
                        </li>
                    </UL>

                    <H2>Your rights</H2>
                    <P>Under the GDPR, you have the right to:</P>
                    <UL>
                        <li>Access the personal data we hold about you.</li>
                        <li>Correct inaccurate data.</li>
                        <li>
                            Request erasure of your personal data, subject to
                            our legal retention obligations above.
                        </li>
                        <li>Restrict or object to certain processing.</li>
                        <li>Receive your data in a portable format.</li>
                        <li>
                            Withdraw consent at any time, where processing is
                            based on consent.
                        </li>
                        <li>
                            Lodge a complaint with the Finnish Data Protection
                            Ombudsman (or your local supervisory authority).
                        </li>
                    </UL>
                    <P>
                        To exercise any of these rights, contact{' '}
                        <a
                            href="mailto:privacy@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            privacy@hetkin.app
                        </a>
                        . If you&rsquo;re a customer-portal user, your
                        organization&rsquo;s admin can also request this on your
                        behalf.
                    </P>

                    <H2>Security</H2>
                    <P>
                        We take reasonable technical and organizational measures
                        to protect your data. See our{' '}
                        <Link
                            href={route('trust')}
                            className="hover:text-navy-900 underline"
                        >
                            Security
                        </Link>{' '}
                        page for more detail.
                    </P>

                    <H2>Changes to this policy</H2>
                    <P>
                        We may update this policy as the product and our
                        practices evolve, especially during this pilot stage.
                        We&rsquo;ll update the effective date above when we do,
                        and let active customers know of any material change.
                    </P>

                    <H2>Contact us</H2>
                    <P>
                        Questions about this policy or your data:{' '}
                        <a
                            href="mailto:privacy@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            privacy@hetkin.app
                        </a>
                    </P>
                </div>
            </main>
            <SiteFooter />
        </div>
    );
}
