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

export default function Terms() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Terms of Service" />
            <SiteHeader />
            <main className="flex-1 px-6 py-16">
                <div className="mx-auto max-w-[720px]">
                    <h1 className="font-display text-navy-900 text-3xl font-semibold">
                        Terms of Service
                    </h1>
                    <p className="text-ink-500 mt-3 text-sm">
                        Effective date:{' '}
                        <span className="text-ink-900 font-medium">
                            [to be confirmed on publication]
                        </span>
                        . These terms are written for Hetkin&rsquo;s current
                        pilot stage and will be expanded as the product and our
                        commercial terms mature.
                    </p>

                    <P>
                        These Terms of Service (&ldquo;Terms&rdquo;) govern
                        access to and use of Hetkin&rsquo;s website and customer
                        portal (together, the &ldquo;Service&rdquo;), provided
                        by Hetkin Oy (&ldquo;Hetkin,&rdquo; &ldquo;we&rdquo;).
                        By using the Service, your organization and its users
                        agree to these Terms. If you&rsquo;re using the Service
                        on behalf of a company, you confirm you have authority
                        to accept these Terms for that company.
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
                    </UL>

                    <H2>About the Service</H2>
                    <P>
                        Hetkin helps manufacturing companies align production
                        schedules with electricity prices to reduce costs. The
                        Service is currently offered as part of a limited pilot
                        program to a small number of customers, under
                        individually agreed pilot terms (scope, duration, and
                        fees) that supplement these Terms. Where a signed pilot
                        agreement conflicts with these Terms, the pilot
                        agreement controls.
                    </P>

                    <H2>Accounts and access</H2>
                    <UL>
                        <li>
                            Accounts are provisioned by Hetkin staff or by your
                            organization&rsquo;s admin — there is no public
                            self-service sign-up.
                        </li>
                        <li>
                            You&rsquo;re responsible for the accuracy of
                            information your users provide and for keeping login
                            credentials confidential.
                        </li>
                        <li>
                            Your organization&rsquo;s admin is responsible for
                            managing which users at your company have access,
                            and at what role.
                        </li>
                    </UL>

                    <H2>The pilot program</H2>
                    <P>
                        Submitting a pilot application does not guarantee
                        acceptance. We review applications and reach out to
                        qualifying companies to discuss scope, timeline, and
                        pricing before any commitment is made. Pilot terms —
                        including duration, the facilities and data covered, and
                        fees — are agreed separately with each pilot customer.
                    </P>

                    <H2>Savings figures and recommendations</H2>
                    <P>
                        Hetkin generates schedule recommendations and savings
                        estimates based on electricity price data and the
                        information your team provides (including uploaded
                        consumption data and entered schedules). These are
                        estimates, not guarantees:
                    </P>
                    <UL>
                        <li>
                            Savings assessments are marked <em>provisional</em>{' '}
                            until there&rsquo;s enough operating history to lock
                            a verified baseline. Provisional figures can change
                            as more data comes in.
                        </li>
                        <li>
                            Actual savings depend on factors outside our
                            control, including how closely your team follows a
                            recommendation, electricity market conditions, and
                            changes to your production process.
                        </li>
                        <li>
                            Recommendations are decision support for your team,
                            not automated control of your equipment — a person
                            on your side decides whether to act on them.
                        </li>
                    </UL>

                    <H2>Your data</H2>
                    <P>
                        You retain ownership of the data you upload or enter
                        into Hetkin (consumption data, schedules, and related
                        business information). You grant us the right to use it
                        to provide and improve the Service. You&rsquo;re
                        responsible for having the right to share any data you
                        upload with us. See our{' '}
                        <Link
                            href={route('legal.privacy')}
                            className="hover:text-navy-900 underline"
                        >
                            Privacy Policy
                        </Link>{' '}
                        for how we handle personal data.
                    </P>

                    <H2>Fees and payment</H2>
                    <P>
                        Pilot pricing is agreed individually and typically
                        combines a base fee with a share of verified savings,
                        invoiced by Hetkin and payable by bank transfer on the
                        terms stated on the invoice. We do not currently process
                        card payments through the Service. Specific rates,
                        invoicing frequency, and payment terms are set out in
                        your pilot agreement.
                    </P>

                    <H2>Acceptable use</H2>
                    <P>You agree not to:</P>
                    <UL>
                        <li>
                            Attempt to access another organization&rsquo;s data
                            or accounts.
                        </li>
                        <li>
                            Interfere with or attempt to disrupt the Service or
                            its underlying infrastructure.
                        </li>
                        <li>
                            Use the Service for any purpose that violates
                            applicable law.
                        </li>
                        <li>
                            Reverse-engineer or attempt to extract the
                            underlying algorithms of the Service.
                        </li>
                    </UL>

                    <H2>Intellectual property</H2>
                    <P>
                        Hetkin and its licensors own all rights in the Service,
                        including its software, algorithms, and branding. These
                        Terms don&rsquo;t transfer any of that to you — you get
                        a limited right to use the Service as intended, for as
                        long as your account or pilot agreement is active.
                    </P>

                    <H2>Confidentiality</H2>
                    <P>
                        Each party will keep the other&rsquo;s non-public
                        business information confidential and use it only to
                        perform under these Terms, except where disclosure is
                        required by law.
                    </P>

                    <H2>Disclaimers</H2>
                    <P>
                        The Service is provided on an &ldquo;as is&rdquo; basis
                        during this pilot stage. We don&rsquo;t warrant that the
                        Service will be uninterrupted, error-free, or that any
                        particular level of savings will be achieved. Nothing in
                        the Service constitutes professional energy-procurement,
                        engineering, or financial advice — production scheduling
                        decisions remain your responsibility.
                    </P>

                    <H2>Limitation of liability</H2>
                    <P>
                        To the extent permitted by law, Hetkin&rsquo;s total
                        liability arising from these Terms or use of the Service
                        is limited to the fees you&rsquo;ve paid us in the three
                        months preceding the claim. Neither party is liable for
                        indirect, incidental, or consequential damages. This
                        section doesn&rsquo;t limit liability where the law
                        doesn&rsquo;t allow it to be limited (for example,
                        liability for gross negligence or willful misconduct).
                    </P>

                    <H2>Term and termination</H2>
                    <P>
                        These Terms apply for as long as you have an active
                        account or pilot agreement. Either party may end a pilot
                        in line with the notice period set out in the pilot
                        agreement. We may suspend or terminate access if these
                        Terms are materially breached and not fixed within a
                        reasonable time after notice.
                    </P>

                    <H2>Changes to these Terms</H2>
                    <P>
                        We may update these Terms as the Service and our pilot
                        program evolve. We&rsquo;ll update the effective date
                        above and notify active customers of material changes.
                    </P>

                    <H2>Governing law</H2>
                    <P>
                        These Terms are governed by the laws of Finland.
                        Disputes will be resolved in the competent courts of
                        Finland, unless your pilot agreement specifies
                        otherwise.
                    </P>

                    <H2>Contact us</H2>
                    <P>
                        Questions about these Terms:{' '}
                        <a
                            href="mailto:hello@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            hello@hetkin.app
                        </a>
                    </P>
                </div>
            </main>
            <SiteFooter />
        </div>
    );
}
