import { cn } from '@/lib/utils';

/**
 * Shared loading glyph — ported 1:1 from the Next.js app's
 * src/components/ui/spinner.tsx. Uses the brand's accent color for the
 * active arc — "Progress" is one of the explicit uses called out for
 * Indigo in the design spec, not a neutral navy.
 *
 * Colors are overridable via `className` (default assumes a light
 * background; pass e.g. "border-white/25 border-t-white" on a dark
 * surface).
 */
export function Spinner({
    className,
    label = 'Loading',
}: {
    className?: string;
    label?: string;
}) {
    return (
        <span
            role="status"
            aria-label={label}
            className={cn(
                'border-border border-t-accent inline-block h-4 w-4 flex-shrink-0 animate-spin rounded-full border-2',
                className,
            )}
        />
    );
}
