import Eyebrow from '@/Components/marketing/Eyebrow';
import FaqAccordion from '@/Components/marketing/FaqAccordion';
import SiteFooter from '@/Components/marketing/SiteFooter';
import SiteHeader from '@/Components/marketing/SiteHeader';
import { buttonVariants } from '@/Components/ui/Button';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';

export default function Faq() {
    return (
        <div className="flex min-h-screen flex-col">
            <Head title="FAQ" />
            <SiteHeader />
            <main className="flex-1">
                <section className="px-6 py-16">
                    <div className="text-center">
                        <Eyebrow>FAQ</Eyebrow>
                        <h1 className="font-display text-navy-900 text-3xl font-semibold sm:text-4xl">
                            Common questions before applying
                        </h1>
                    </div>
                    <FaqAccordion />
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
                            href={route('pilot-program.index')}
                            className={cn(
                                buttonVariants({
                                    variant: 'secondary',
                                    size: 'lg',
                                }),
                                'border-white/30 bg-transparent text-white hover:bg-white/10',
                            )}
                        >
                            Read the FAQ first
                        </Link>
                    </div>
                </section>
            </main>
            <SiteFooter />
        </div>
    );
}
