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
    };
};
