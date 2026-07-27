import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

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

        root.render(<App {...props} />);
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
