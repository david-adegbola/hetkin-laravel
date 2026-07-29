import { Button } from '@/Components/ui/Button';
import * as Sentry from '@sentry/react';
import { Component, ErrorInfo, ReactNode } from 'react';

type Realm = 'portal' | 'admin' | 'default';

/**
 * Reads window.location directly rather than Inertia's `subdomain` shared
 * prop — this boundary has to keep working even if the render that threw
 * happened before/without a usable page context, the same reasoning v2's
 * own src/lib/subdomain-client.ts gives for not relying on `headers()` in
 * a Client Component. Mirrors that file's `isDashboardHost`/`isAdminHost`
 * prefix check plus the bare-path form (routes/domains.php).
 */
function detectRealm(): Realm {
    if (typeof window === 'undefined') {
        return 'default';
    }
    const { hostname, pathname } = window.location;
    if (
        hostname.startsWith('dashboard.') ||
        pathname.startsWith('/dashboard')
    ) {
        return 'portal';
    }
    if (hostname.startsWith('admin.') || pathname.startsWith('/admin')) {
        return 'admin';
    }
    return 'default';
}

interface State {
    hasError: boolean;
}

/**
 * Consolidates the Next.js app's four route-segment error boundaries
 * (src/app/error.tsx, global-error.tsx, (portal)/error.tsx,
 * admin/(protected)/error.tsx) into one component. Inertia has no
 * per-route-segment boundary convention the way the App Router's
 * `error.tsx` file placement does — there's no nested layout tree for
 * "portal errors" vs "admin errors" to attach to separately — so this
 * wraps the whole app once (resources/js/app.tsx) and picks its fallback
 * copy/destination at render time from `detectRealm()` above, which is
 * the same three-way split (marketing/default, portal, admin) v2's four
 * files encode structurally.
 *
 * Reports via Sentry.captureException, same as every one of v2's four
 * boundaries — a no-op when VITE_SENTRY_DSN is unset (resources/js/lib/
 * sentry.ts).
 */
export class ErrorBoundary extends Component<{ children: ReactNode }, State> {
    state: State = { hasError: false };

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
        Sentry.captureException(error, {
            contexts: { react: { componentStack: errorInfo.componentStack } },
        });
    }

    private reset = (): void => {
        this.setState({ hasError: false });
    };

    render() {
        if (!this.state.hasError) {
            return this.props.children;
        }

        const realm = detectRealm();
        const heading = realm === 'admin' ? 'text-xl' : 'text-2xl';
        const body =
            realm === 'admin'
                ? 'This page hit an unexpected error. It has been reported — try again, or use the nav above to go elsewhere.'
                : realm === 'portal'
                  ? 'This page hit an unexpected error. It has been reported to our team — try again, or head back to the dashboard.'
                  : 'This page hit an unexpected error. It has been reported — try again, or head back to the homepage.';
        const secondaryHref = realm === 'portal' ? '/dashboard' : '/';
        const secondaryLabel =
            realm === 'portal' ? 'Back to dashboard' : 'Go home';

        return (
            <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 px-6 text-center">
                <h1
                    className={`font-display text-primary font-semibold ${heading}`}
                >
                    Something went wrong
                </h1>
                <p className="text-text-secondary max-w-md">{body}</p>
                <div className="flex gap-3">
                    <Button onClick={this.reset}>Try again</Button>
                    {realm !== 'admin' && (
                        <Button
                            variant="secondary"
                            onClick={() =>
                                (window.location.href = secondaryHref)
                            }
                        >
                            {secondaryLabel}
                        </Button>
                    )}
                </div>
            </div>
        );
    }
}
