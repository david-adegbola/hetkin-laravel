import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * React equivalent of the Next.js app's src/lib/subdomain-server.ts /
 * subdomain-client.ts (MIGRATION_PLAN.md ADR-21) — lets a component build a
 * subdomain-correct href instead of hardcoding a "/dashboard" or "/admin"
 * prefix. `${usePortalBasePath()}/schedule` resolves to the clean
 * "/schedule" on dashboard.{domain} (routes/domains.php's bare-path
 * registrations) and to "/dashboard/schedule" everywhere else, matching
 * v2's exact behavior — same reasoning applies to useAdminBasePath().
 *
 * Reads the `subdomain` prop shared by every Inertia response
 * (App\Http\Middleware\HandleInertiaRequests::share(), computed server-side
 * from the request's Host header) rather than window.location: Inertia has
 * no Server/Client component split for that distinction to live in, and a
 * shared prop keeps this correct on the very first server-rendered
 * response too, before any client-side JS has run.
 */
export function usePortalBasePath(): string {
    const { subdomain } = usePage<PageProps>().props;
    return subdomain.isDashboardHost ? '' : '/dashboard';
}

export function useAdminBasePath(): string {
    const { subdomain } = usePage<PageProps>().props;
    return subdomain.isAdminHost ? '' : '/admin';
}
