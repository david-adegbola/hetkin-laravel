import { Link } from '@inertiajs/react';

/** Marketing-site footer — ported 1:1 from the Next.js app's src/components/SiteFooter.tsx. */
export default function SiteFooter() {
    return (
        <footer className="border-line-100 border-t py-10">
            <div className="text-ink-500 mx-auto flex max-w-[1200px] flex-col items-center justify-between gap-4 px-6 text-sm md:flex-row">
                <p>
                    &copy; {new Date().getFullYear()} Hetkin Oy. Vaasa, Finland.
                    Backed by Vaasa Startup Factory.
                </p>
                <div className="flex flex-wrap items-center gap-5">
                    <Link
                        href={route('legal.privacy')}
                        className="hover:text-navy-900"
                    >
                        Privacy Policy
                    </Link>
                    <Link
                        href={route('legal.terms')}
                        className="hover:text-navy-900"
                    >
                        Terms of Service
                    </Link>
                    <Link
                        href={route('legal.cookies')}
                        className="hover:text-navy-900"
                    >
                        Cookie Policy
                    </Link>
                    <Link href={route('trust')} className="hover:text-navy-900">
                        Security
                    </Link>
                </div>
            </div>
        </footer>
    );
}
