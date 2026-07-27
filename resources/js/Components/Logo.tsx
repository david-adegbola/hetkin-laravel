import { cn } from '@/lib/utils';

/**
 * Single source of truth for the Hetkin mark — ported 1:1 from the Next.js
 * app's src/components/Logo.tsx (same four SVG paths).
 *
 * Inlined as JSX rather than an <img> so it can inherit `currentColor`
 * (lets it flip color on a dark surface without "recoloring" the mark
 * itself) and so it never causes a second network request.
 *
 * `LogoMark` is the symbol alone (sidebar rail, anywhere space is tight).
 * `Logo` is the full lockup (mark + wordmark) for everywhere else. Never
 * stretch either — both only accept a `className` for sizing/positioning.
 * Color follows `currentColor` (defaults to `text-primary` navy, matching
 * every current placement's light background).
 */
export function LogoMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 512 512"
            fill="none"
            aria-hidden="true"
            className={cn('text-primary h-6 w-6 flex-shrink-0', className)}
        >
            <path
                d="M107.42,73.4h123.21c5.52,0,10,4.48,10,10v123.21c0,13.8-11.2,25-25,25h-123.21c-5.52,0-10-4.48-10-10v-123.21c0-13.8,11.2-25,25-25Z"
                fill="currentColor"
            />
            <path
                d="M341.68,75.3h-41.02c-16.18,0-29.3,13.12-29.3,29.3v86.43h50.56v65.92h78.36c16.18,0,29.3-13.12,29.3-29.3v-64.46c0-48.54-39.35-87.89-87.89-87.89Z"
                fill="currentColor"
            />
            <path
                d="M190.08,256.95h-19.76c-48.54,0-87.89,39.35-87.89,87.89v64.46c0,16.18,13.12,29.3,29.3,29.3h99.61c16.18,0,29.3-13.12,29.3-29.3v-86.43h-50.56v-65.92Z"
                fill="currentColor"
            />
            <path
                d="M341.68,280.39h-19.76v42.48h-50.56v86.43c0,16.18,13.12,29.3,29.3,29.3h99.61c16.18,0,29.3-13.12,29.3-29.3v-41.02c0-48.54-39.35-87.89-87.89-87.89Z"
                fill="currentColor"
            />
        </svg>
    );
}

export function Logo({
    className,
    markClassName,
}: {
    className?: string;
    markClassName?: string;
}) {
    return (
        <span
            className={cn(
                'text-primary inline-flex items-center gap-2',
                className,
            )}
        >
            <LogoMark className={markClassName} />
            <span className="font-display text-base font-semibold">Hetkin</span>
        </span>
    );
}
