import '../css/app.css';
import './bootstrap';

import { ErrorBoundary } from '@/Components/ErrorBoundary';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initPostHog } from './lib/analytics';
import { initSentry } from './lib/sentry';

initSentry();
initPostHog();

// Complements App\Http\Middleware\PreventBackHistoryCache (which stops a
// page from being cached in the FIRST place) with a fallback for the rare
// case a browser still momentarily repaints an already-cached page from
// its back-forward cache (bfcache) before this runs — `persisted: true`
// on `pageshow` is exactly that signal. Forcing a fresh visit re-runs
// server-side auth immediately rather than leaving a stale authenticated
// page interactive. See MIGRATION_PLAN.md ADR-14.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        router.reload();
    }
});

// Matches the Next.js app's metadata.title template (src/app/layout.tsx):
// default title with no per-page title set, "%s · Hetkin" otherwise.
const DEFAULT_TITLE = 'Hetkin — Run production when electricity is cheap';

createInertiaApp({
    title: (title) => (title ? `${title} · Hetkin` : DEFAULT_TITLE),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <ErrorBoundary>
                <App {...props} />
            </ErrorBoundary>,
        );
    },
    // The Next.js app has no global top-loading-bar — navigation feedback is
    // per-link (NavLink/PendingLink's instant tap acknowledgement) plus a
    // delayed fade-in for slower route transitions (see the
    // `.loading-fade-in` keyframe in app.css). Both are ported as React
    // components in a later stage; Inertia's own default nprogress-style bar
    // would be a new UI element the original app doesn't have, so it's
    // switched off here rather than just recolored.
    progress: false,
});
