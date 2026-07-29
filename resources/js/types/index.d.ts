export interface User {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string;
    image?: string | null;
}

export type AdminRole =
    'ADMIN' | 'VERIFICATION_ANALYST' | 'SUPPORT' | 'AUDITOR';

export interface AdminUser {
    id: string;
    name: string;
    email: string;
    email_verified_at?: string;
    role: AdminRole;
    active: boolean;
}

export type OrganizationStatus = 'PILOT' | 'ACTIVE' | 'PAUSED' | 'CHURNED';

/**
 * Shared only on portal routes (App\Http\Middleware\
 * ResolveOrganizationContext), never globally — optional here rather than
 * always-present, unlike `auth`/`subdomain`.
 */
export interface PortalOrganization {
    id: string;
    name: string;
    status: OrganizationStatus;
    process_type: string | null;
}

/**
 * `user`/`admin` are both nullable — see App\Http\Middleware\
 * HandleInertiaRequests: exactly one is ever non-null on a given page,
 * depending on which realm rendered it (MIGRATION_PLAN.md ADR-4).
 */
export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User | null;
        admin: AdminUser | null;
    };
    /** See resources/js/lib/subdomain.ts and MIGRATION_PLAN.md ADR-21. */
    subdomain: {
        isDashboardHost: boolean;
        isAdminHost: boolean;
        /** See App\Support\PwaSurface and resources/js/lib/pwa.ts (Stage 14). */
        surface: 'marketing' | 'portal' | 'admin';
    };
    organization?: PortalOrganization;
    /**
     * One-time flash data — see App\Http\Middleware\HandleInertiaRequests.
     */
    flash: {
        created_admin: { email: string; temp_password: string } | null;
        backfill_result: {
            status: 'success' | 'error';
            message: string;
        } | null;
    };
    /** See config/app.php's `version` key and resources/js/lib/pwa.ts (Stage 14). */
    appVersion: string;
};
