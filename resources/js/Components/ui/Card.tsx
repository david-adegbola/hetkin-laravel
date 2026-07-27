import { cn } from '@/lib/utils';
import * as React from 'react';

/**
 * Card — ported 1:1 from the Next.js app's src/components/ui/card.tsx.
 * `rounded-card` (16px token) and a very subtle `shadow-sm` give cards a
 * touch of elevation without looking ephemeral; the border does most of the
 * separation work.
 */
export function Card({
    className,
    ...props
}: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-card border-border bg-surface border p-6 shadow-sm',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({
    className,
    ...props
}: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'mb-4 flex items-center justify-between gap-3',
                className,
            )}
            {...props}
        />
    );
}

export function CardTitle({
    className,
    ...props
}: React.HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h3
            className={cn(
                'font-display text-primary text-base font-semibold',
                className,
            )}
            {...props}
        />
    );
}

export function CardDescription({
    className,
    ...props
}: React.HTMLAttributes<HTMLParagraphElement>) {
    return (
        <p
            className={cn('text-text-secondary text-sm', className)}
            {...props}
        />
    );
}

export function CardContent({
    className,
    ...props
}: React.HTMLAttributes<HTMLDivElement>) {
    return <div className={cn(className)} {...props} />;
}

export function CardFooter({
    className,
    ...props
}: React.HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'border-border mt-4 flex items-center justify-between border-t pt-4',
                className,
            )}
            {...props}
        />
    );
}
