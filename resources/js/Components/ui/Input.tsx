import { cn } from '@/lib/utils';
import * as React from 'react';

/**
 * Inputs — ported 1:1 from the Next.js app's src/components/ui/input.tsx.
 * White fill, light border, `rounded-control` (10px, shared with buttons).
 * Focus is always the brand's one accent color (indigo) — never the browser
 * default blue.
 */
const FIELD_BASE =
    'rounded-control border border-border bg-background text-sm text-primary placeholder:text-text-secondary/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent focus-visible:border-accent disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-error aria-invalid:ring-error/30';

export const Input = React.forwardRef<
    HTMLInputElement,
    React.InputHTMLAttributes<HTMLInputElement>
>(({ className, type = 'text', ...props }, ref) => {
    return (
        <input
            ref={ref}
            type={type}
            className={cn('h-11 w-full px-3.5', FIELD_BASE, className)}
            {...props}
        />
    );
});
Input.displayName = 'Input';

export const Textarea = React.forwardRef<
    HTMLTextAreaElement,
    React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, ...props }, ref) => {
    return (
        <textarea
            ref={ref}
            className={cn('w-full px-3.5 py-2.5', FIELD_BASE, className)}
            {...props}
        />
    );
});
Textarea.displayName = 'Textarea';

export const Select = React.forwardRef<
    HTMLSelectElement,
    React.SelectHTMLAttributes<HTMLSelectElement>
>(({ className, ...props }, ref) => {
    return (
        <select
            ref={ref}
            className={cn('h-11 w-full px-3.5', FIELD_BASE, className)}
            {...props}
        />
    );
});
Select.displayName = 'Select';

export const Label = React.forwardRef<
    HTMLLabelElement,
    React.LabelHTMLAttributes<HTMLLabelElement>
>(({ className, ...props }, ref) => (
    <label
        ref={ref}
        className={cn(
            'text-primary mb-1.5 block text-sm font-medium',
            className,
        )}
        {...props}
    />
));
Label.displayName = 'Label';

export function FieldError({ children }: { children?: React.ReactNode }) {
    if (!children) return null;
    return (
        <p role="alert" className="text-error mt-1.5 text-xs font-medium">
            {children}
        </p>
    );
}

export function FieldHint({ children }: { children?: React.ReactNode }) {
    if (!children) return null;
    return <p className="text-text-secondary mt-1.5 text-xs">{children}</p>;
}
