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

/**
 * `as` defaults to `h3` — correct for every card nested inside a page that
 * already has its own `<h1>` (PortalPageHeader/AdminPageHeader, or a
 * page-specific `<h1>`), which is most of them. A handful of standalone
 * pages with no shared app shell (Auth/Login, Auth/ForgotPassword,
 * Auth/ResetPassword, Auth/ConfirmPassword, Admin/Auth/Login,
 * Invite/Accept) use this Card's title as the page's *only* heading —
 * those pass `as="h1"` explicitly so the page has a real `<h1>` instead of
 * skipping straight to `h3` (a real WCAG 1.3.1/2.4.6 gap, not a style
 * nitpick: it breaks heading-level navigation for screen reader users).
 */
export function CardTitle({
    as: Component = 'h3',
    className,
    ...props
}: React.HTMLAttributes<HTMLHeadingElement> & { as?: 'h1' | 'h2' | 'h3' }) {
    return (
        <Component
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
