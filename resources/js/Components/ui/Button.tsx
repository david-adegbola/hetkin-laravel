import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

/**
 * Button — ported 1:1 from the Next.js app's src/components/ui/button.tsx.
 * Every variant maps to the brand spec:
 *   - primary: indigo fill, white text — the ONE loud color in the app.
 *     Hover is a darker indigo (--color-accent-hover); active/pressed goes
 *     one step further (--color-accent-active).
 *   - secondary: white fill, navy text, border — never a filled dark
 *     button; navy is reserved for text/nav/icons, not button fills.
 *   - ghost: transparent, light-gray hover.
 *   - destructive: the brand's one red, --color-error — never a gradient.
 * Radius is `rounded-control` (10px, shared with inputs), not Tailwind's
 * default `rounded-lg` (8px) — see app.css.
 *
 * 44px minimum height on all sizes but `icon`/`sm` is deliberate: WCAG 2.2
 * target-size guidance, and this product is used on shop-floor tablets as
 * much as desktop.
 */
export const buttonVariants = cva(
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control text-sm font-medium transition-colors duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary:
                    'bg-accent text-white hover:bg-accent-hover active:bg-accent-active',
                secondary:
                    'bg-background text-primary border border-border hover:bg-surface active:bg-border/40',
                destructive:
                    'bg-error text-white hover:bg-error/90 active:bg-error/80',
                ghost: 'bg-transparent text-text-secondary hover:bg-surface hover:text-primary',
            },
            size: {
                default: 'h-11 px-5',
                sm: 'h-9 px-3.5 text-[13px]',
                lg: 'h-12 px-6 text-base',
                icon: 'h-11 w-11',
            },
        },
        defaultVariants: {
            variant: 'primary',
            size: 'default',
        },
    },
);

export interface ButtonProps
    extends
        React.ButtonHTMLAttributes<HTMLButtonElement>,
        VariantProps<typeof buttonVariants> {}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
    ({ className, variant, size, ...props }, ref) => {
        return (
            <button
                ref={ref}
                className={cn(buttonVariants({ variant, size, className }))}
                {...props}
            />
        );
    },
);
Button.displayName = 'Button';
