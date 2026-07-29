import * as Sentry from '@sentry/react';

/**
 * Ports the Next.js app's client-side Sentry init exactly
 * (src/instrumentation-client.ts) — `dsn` is deliberately just an env var,
 * no fallback string: if VITE_SENTRY_DSN isn't set, the SDK no-ops rather
 * than throwing or sending anything anywhere, same "log instead of crash"
 * posture as every other optional integration here (Resend, PostHog).
 *
 * `tracesSampleRate` matches v2's own dev/prod split
 * (`NODE_ENV === "development" ? 1.0 : 0.1`) — `import.meta.env.DEV` is
 * Vite's own equivalent signal.
 *
 * Deliberately scoped to error monitoring only, same as v2: no Session
 * Replay, no user-feedback widget — that's a real product/privacy
 * decision (Replay captures DOM content) this port isn't making
 * speculatively either.
 */
export function initSentry(): void {
    const dsn = import.meta.env.VITE_SENTRY_DSN;
    if (!dsn) {
        return;
    }

    Sentry.init({
        dsn,
        environment: import.meta.env.MODE,
        tracesSampleRate: import.meta.env.DEV ? 1.0 : 0.1,
    });
}
