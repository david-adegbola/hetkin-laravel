import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

/**
 * Badge — ported 1:1 from the Next.js app's src/components/ui/badge.tsx.
 * Generic status pill. The cheap/mid/expensive triad must never be
 * color-only (~8% of men have some form of color vision deficiency, and
 * this is the one part of the product where misreading a color could cost
 * someone money) — every variant pairs color with a text label and, for
 * price tiers, a shape prefix.
 */
const badgeVariants = cva(
    // Radius: `rounded-full` already equals the brand's 999px badge token, no
    // separate radius variable needed.
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
    {
        variants: {
            variant: {
                neutral: 'bg-border/60 text-text-secondary',
                cheap: 'bg-success-subtle text-success',
                mid: 'bg-warning-subtle text-warning',
                expensive: 'bg-error-subtle text-error',
                navy: 'bg-primary/5 text-primary',
            },
        },
        defaultVariants: {
            variant: 'neutral',
        },
    },
);

export interface BadgeProps
    extends
        React.HTMLAttributes<HTMLSpanElement>,
        VariantProps<typeof badgeVariants> {
    /** Shape glyph shown before the label so meaning never relies on color alone. */
    showGlyph?: boolean;
}

const GLYPH: Record<string, string> = {
    cheap: '●',
    mid: '▲',
    expensive: '■',
    neutral: '',
    navy: '',
};

export function Badge({
    className,
    variant,
    showGlyph = true,
    children,
    ...props
}: BadgeProps) {
    return (
        <span className={cn(badgeVariants({ variant, className }))} {...props}>
            {showGlyph && variant && GLYPH[variant] ? (
                <span aria-hidden="true" className="text-[9px]">
                    {GLYPH[variant]}
                </span>
            ) : null}
            {children}
        </span>
    );
}

/** Price-tier classification shared by charts, schedule views, and recommendations. */
export type PriceTier = 'cheap' | 'mid' | 'expensive';

export function classifyPriceTier(
    eurPerMwh: number,
    thresholds: { cheapBelow: number; expensiveAbove: number } = {
        cheapBelow: 40,
        expensiveAbove: 58,
    },
): PriceTier {
    if (eurPerMwh < thresholds.cheapBelow) return 'cheap';
    if (eurPerMwh > thresholds.expensiveAbove) return 'expensive';
    return 'mid';
}
