import { type ReactNode } from 'react';

/** Ported 1:1 from the Next.js app's src/components/Eyebrow.tsx. */
export default function Eyebrow({ children }: { children: ReactNode }) {
    return (
        <span className="border-line-100 bg-surface-0 text-navy-700 mb-4 inline-block rounded-full border px-3.5 py-1.5 text-xs font-semibold tracking-wide uppercase">
            {children}
        </span>
    );
}
