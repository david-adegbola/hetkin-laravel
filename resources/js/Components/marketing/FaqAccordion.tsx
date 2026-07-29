import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { ReactNode, useState } from 'react';

/** Ported 1:1 from the Next.js app's src/components/FaqAccordion.tsx. */
const FAQS: Array<{ q: string; a: ReactNode }> = [
    {
        q: 'How is "savings" actually calculated?',
        a: (
            <>
                We agree a baseline with you before your pilot starts — a
                documented estimate of what your electricity cost would have
                been without Hetkin&apos;s recommendations. Verified savings are
                the measured difference between that locked baseline and your
                actual metered cost each month. See our{' '}
                <Link
                    href={route('methodology')}
                    className="hover:text-navy-900 underline"
                >
                    Methodology
                </Link>{' '}
                page for the full calculation and the internal review it goes
                through before it&apos;s billed.
            </>
        ),
    },
    {
        q: "What if we don't follow a recommendation?",
        a: 'You choose which recommendations to act on. Every suggestion shows a delivery-risk indicator alongside its savings estimate, and you can reject with a reason at any time. We only bill on savings you actually realize.',
    },
    {
        q: 'Do you access our production systems directly?',
        a: "No. In the current version of Hetkin, you review and apply schedule changes yourself. We read metering and schedule data with your consent — we don't write to your ERP or control equipment.",
    },
    {
        q: 'What does the pilot cost?',
        a: (
            <>
                Applying is free. If approved, pilots run on a small platform
                fee plus a share of verified savings — pricing is confirmed with
                you before anything is billed, and there is no performance fee
                if there is no saving. See{' '}
                <Link
                    href={route('pricing')}
                    className="hover:text-navy-900 underline"
                >
                    Pricing
                </Link>{' '}
                for how that&apos;s structured.
            </>
        ),
    },
    {
        q: 'What happens to our data if we stop?',
        a: "You can revoke data access at any time. We'll confirm deletion timelines in line with our data processing agreement — your data is never repurposed for other customers.",
    },
];

export default function FaqAccordion() {
    const [openIndex, setOpenIndex] = useState<number | null>(null);

    return (
        <div className="mx-auto mt-10 max-w-[760px]">
            {FAQS.map((item, i) => {
                const open = openIndex === i;
                return (
                    <div key={item.q} className="border-line-100 border-b">
                        <button
                            type="button"
                            onClick={() => setOpenIndex(open ? null : i)}
                            aria-expanded={open}
                            className="font-display text-navy-900 flex w-full items-center justify-between gap-4 py-4.5 text-left text-base font-semibold"
                        >
                            {item.q}
                            <ChevronDown
                                aria-hidden="true"
                                strokeWidth={2}
                                className={`text-text-secondary h-4 w-4 flex-shrink-0 transition-transform duration-200 ease-out ${open ? 'rotate-180' : ''}`}
                            />
                        </button>
                        {open && (
                            <p className="text-ink-500 pb-4.5 text-sm">
                                {item.a}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
