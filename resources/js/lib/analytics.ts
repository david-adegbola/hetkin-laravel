import posthog from 'posthog-js';

/**
 * Ports the Next.js app's PostHogProvider.tsx client-side init exactly
 * (src/components/PostHogProvider.tsx) — autocaptures pageviews (the
 * "visitor" stage of the Phase 1 §8 funnel). Everything past that stage
 * is captured server-side (App\Domain\Analytics\Services\PostHogService),
 * which is where most of this app's meaningful events actually happen.
 *
 * No-ops cleanly if VITE_POSTHOG_KEY isn't set — same posture as every
 * other optional integration in this app (Resend, Sentry). Called once
 * from resources/js/app.tsx's bootstrap rather than wrapped as a
 * `<PostHogProvider>` component: nothing in this port calls posthog-js's
 * `usePostHog()` hook, so there's no component tree that needs the
 * context v2's wrapper provided — a plain init is the same observable
 * behavior with less machinery.
 *
 * Inertia navigations use the History API (pushState), same as a Next.js
 * App Router client-side transition — posthog-js's `capture_pageview`
 * autocapture already listens for exactly that, so no extra Inertia
 * router event wiring is needed for each navigation to count as a
 * pageview.
 */
export function initPostHog(): void {
    const key = import.meta.env.VITE_POSTHOG_KEY;
    if (!key || posthog.__loaded) {
        return;
    }

    posthog.init(key, {
        api_host:
            import.meta.env.VITE_POSTHOG_HOST || 'https://eu.i.posthog.com',
        capture_pageview: true,
        person_profiles: 'identified_only',
    });
}
