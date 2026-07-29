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

export default function Cookies() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="Cookie Policy" />
            <SiteHeader />
            <main className="flex-1 px-6 py-16">
                <div className="mx-auto max-w-[720px]">
                    <h1 className="font-display text-navy-900 text-3xl font-semibold">
                        Cookie Policy
                    </h1>
                    <p className="text-ink-500 mt-3 text-sm">
                        Effective date:{' '}
                        <span className="text-ink-900 font-medium">
                            [to be confirmed on publication]
                        </span>
                        .
                    </p>

                    <P>
                        Cookies are small text files stored on your device when
                        you visit a website. This policy explains which cookies
                        Hetkin uses, why, and how you can control them.
                    </P>

                    <H2>Essential cookies</H2>
                    <P>
                        These keep the Service working and secure. We
                        can&rsquo;t offer sign-in or a working portal without
                        them, so they&rsquo;re not subject to an opt-out — using
                        Hetkin means using these.
                    </P>
                    <div className="rounded-card border-line-100 mt-4 overflow-hidden border">
                        <table className="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr className="border-line-100 bg-surface-50 border-b">
                                    <th className="text-navy-900 px-4 py-2.5 font-medium">
                                        Cookie
                                    </th>
                                    <th className="text-navy-900 px-4 py-2.5 font-medium">
                                        Purpose
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="text-ink-500">
                                <tr className="border-line-100 border-b">
                                    <td className="px-4 py-2.5 align-top font-mono text-xs">
                                        hetkin_customer.*
                                    </td>
                                    <td className="px-4 py-2.5 align-top">
                                        Keeps customer-portal users signed in
                                        and their session secure.
                                    </td>
                                </tr>
                                <tr>
                                    <td className="px-4 py-2.5 align-top font-mono text-xs">
                                        hetkin_admin.*
                                    </td>
                                    <td className="px-4 py-2.5 align-top">
                                        Keeps Hetkin staff signed in to the
                                        internal admin tools, separately from
                                        customer sessions.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <H2>Non-essential cookies</H2>
                    <P>
                        With your consent, we use product analytics (PostHog,
                        hosted in the EU) to understand how the portal and
                        website are used — for example, which pages get visited
                        and whether a feature is actually being used — so we can
                        prioritize what to build next. This is not currently
                        enabled on every environment and only runs where a valid
                        analytics key is configured.
                    </P>
                    <P>
                        We don&rsquo;t currently use advertising or marketing
                        cookies, and we don&rsquo;t sell data collected through
                        analytics to anyone.
                    </P>

                    <H2>Cookie consent and managing your preferences</H2>
                    <P>
                        We are rolling out a cookie consent banner that lets you
                        accept all cookies, reject non-essential ones, or choose
                        which categories to allow. Until then, non-essential
                        cookies are only active where analytics is explicitly
                        configured, and you can always block or delete cookies
                        through your browser&rsquo;s settings — note that
                        blocking essential cookies will prevent sign-in and most
                        of the portal from working. Once the banner is live,
                        you&rsquo;ll be able to change your choice at any time
                        from a &ldquo;Cookie preferences&rdquo; link on the
                        site.
                    </P>

                    <H2>Changes to this policy</H2>
                    <P>
                        We may update this policy as our use of cookies changes,
                        particularly once the consent banner described above is
                        live. We&rsquo;ll update the effective date above when
                        we do.
                    </P>

                    <H2>Contact us</H2>
                    <P>
                        Questions about this policy:{' '}
                        <a
                            href="mailto:privacy@hetkin.app"
                            className="hover:text-navy-900 underline"
                        >
                            privacy@hetkin.app
                        </a>
                        . See also our{' '}
                        <Link
                            href={route('legal.privacy')}
                            className="hover:text-navy-900 underline"
                        >
                            Privacy Policy
                        </Link>
                        .
                    </P>
                </div>
            </main>
            <SiteFooter />
        </div>
    );
}
