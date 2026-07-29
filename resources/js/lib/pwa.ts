import type { PageProps } from '@/types';
import { useEffect, useState } from 'react';

/**
 * MIGRATION_PLAN.md Stage 14 — Service Worker registration + update
 * detection for the two installable portals.
 *
 * `syncPwaForPage()` is the ongoing source of truth, called from
 * resources/js/app.tsx on EVERY Inertia navigation (`router.on('navigate',
 * ...)`), not just once at boot. That's a deliberate fix for a real gap
 * found during manual testing: Inertia is an SPA — after the very first
 * full page load, it never re-renders resources/views/app.blade.php, so a
 * <head> computed once at initial load (e.g. on the sign-in page, before
 * the visitor is authenticated) would otherwise never pick up a
 * subsequent in-app login, and a manifest link added pre-login would
 * likewise never get removed after an in-app sign-out. blade's own gate
 * still matters for the very first paint (what a crawler or hard reload
 * sees before any JS runs), but this function is what keeps things
 * correct across the SPA session after that. It reads the SAME
 * `subdomain.isDashboardHost`/`isAdminHost` booleans already shared on
 * every Inertia response (App\Http\Middleware\HandleInertiaRequests) —
 * not a re-derivation of App\Support\PwaSurface's host/domain logic, just
 * the trivial "subdomain → bare scope, else → prefixed scope" mapping off
 * an already-server-computed boolean.
 */

declare global {
    interface Window {
        __PWA__?: { surface: 'portal' | 'admin'; scope: string } | null;
    }
}

function registerServiceWorker(
    surface: 'portal' | 'admin',
    scope: string,
): void {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const scriptUrl = surface === 'portal' ? '/sw-portal.js' : '/sw-admin.js';

    navigator.serviceWorker.register(scriptUrl, { scope }).catch(() => {
        // Swallow — a failed registration shouldn't break the page. The
        // app works identically with no Service Worker, just without
        // install/offline support.
    });
}

export function syncPwaForPage(props: PageProps): void {
    const surface = props.subdomain.surface;
    const authenticated =
        surface === 'portal'
            ? Boolean(props.auth.user)
            : surface === 'admin'
              ? Boolean(props.auth.admin)
              : false;

    const existingLink = document.querySelector<HTMLLinkElement>(
        'link[rel="manifest"]',
    );

    if (surface === 'marketing' || !authenticated) {
        existingLink?.remove();
        window.__PWA__ = null;

        return;
    }

    const href = `/manifest-${surface}.webmanifest`;

    if (!existingLink || existingLink.getAttribute('href') !== href) {
        existingLink?.remove();
        const link = document.createElement('link');
        link.rel = 'manifest';
        link.href = href;
        document.head.appendChild(link);
    }

    const isSubdomainHost =
        surface === 'portal'
            ? props.subdomain.isDashboardHost
            : props.subdomain.isAdminHost;
    const scope = isSubdomainHost
        ? '/'
        : `/${surface === 'portal' ? 'dashboard' : 'admin'}/`;

    if (
        window.__PWA__?.surface !== surface ||
        window.__PWA__?.scope !== scope
    ) {
        window.__PWA__ = { surface, scope };
        registerServiceWorker(surface, scope);
    }
}

/**
 * Update detection is purely the browser's native Service Worker
 * install/waiting lifecycle — NOT a version-string comparison against
 * config('app.version'). Comparing version strings computed independently
 * on the JS build side vs. the PHP request side is exactly the kind of
 * thing that can silently drift in a real deploy (see config/app.php's
 * `version` key docblock); the SW's own byte-content diffing has no such
 * failure mode and needs nothing baked in at build time.
 */
export function usePwaUpdate(scope: string | null): {
    updateAvailable: boolean;
    applyUpdate: () => void;
} {
    const [registration, setRegistration] =
        useState<ServiceWorkerRegistration | null>(null);
    const [updateAvailable, setUpdateAvailable] = useState(false);

    useEffect(() => {
        if (!scope || !('serviceWorker' in navigator)) {
            return;
        }

        let cancelled = false;

        navigator.serviceWorker.getRegistration(scope).then((reg) => {
            if (cancelled || !reg) {
                return;
            }

            setRegistration(reg);

            if (reg.waiting && navigator.serviceWorker.controller) {
                setUpdateAvailable(true);
            }

            reg.addEventListener('updatefound', () => {
                const installing = reg.installing;

                if (!installing) {
                    return;
                }

                installing.addEventListener('statechange', () => {
                    if (
                        installing.state === 'installed' &&
                        navigator.serviceWorker.controller
                    ) {
                        // A controller already existing means this is a
                        // genuine UPDATE, not the very first install on a
                        // fresh page load — only the former should surface
                        // the "new version available" banner.
                        setUpdateAvailable(true);
                    }
                });
            });
        });

        // Ask the browser to re-check for a new Service Worker version.
        // Harmless if nothing's changed — the browser's own fetch for the
        // update check bypasses HTTP cache per spec, and the PwaController
        // route serves sw-*.js with Cache-Control: no-cache regardless.
        const interval = window.setInterval(
            () => {
                navigator.serviceWorker.getRegistration(scope).then((reg) => {
                    reg?.update();
                });
            },
            30 * 60 * 1000,
        );

        return () => {
            cancelled = true;
            window.clearInterval(interval);
        };
    }, [scope]);

    const applyUpdate = () => {
        if (!registration?.waiting) {
            return;
        }

        // Reload happens once, when the NEW worker actually takes control
        // — not immediately on click — so there's no gap where the page is
        // interactive but partway through a worker handoff.
        navigator.serviceWorker.addEventListener(
            'controllerchange',
            () => window.location.reload(),
            { once: true },
        );

        registration.waiting.postMessage('SKIP_WAITING');
    };

    return { updateAvailable, applyUpdate };
}
