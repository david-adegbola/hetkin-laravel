import { Button } from '@/Components/ui/Button';
import type { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * MIGRATION_PLAN.md Stage 14. This is a UX nicety, not the security
 * boundary — the real "only after entering an authenticated portal" gate
 * is server-side, in resources/views/app.blade.php (no manifest link, no
 * controlling Service Worker at all unless authenticated on the matching
 * surface, which is what actually determines whether the BROWSER's own
 * native install affordance appears). Hiding this button alone couldn't
 * have closed that gap even if it tried to.
 *
 * Chromium/Android fires `beforeinstallprompt` when its own installability
 * criteria are met; this button just surfaces that moment instead of
 * requiring the visitor to know about the omnibox icon. iOS Safari never
 * fires that event at all (no programmatic install API exists there), so
 * it gets static "Add to Home Screen" instructions instead.
 */

interface BeforeInstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        // Safari-specific; not in the DOM lib's Navigator type.
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function isIos(): boolean {
    return (
        /iphone|ipad|ipod/i.test(navigator.userAgent) &&
        !(window as typeof window & { MSStream?: unknown }).MSStream
    );
}

export function InstallPwaButton({ surface }: { surface: 'portal' | 'admin' }) {
    const { subdomain, auth } = usePage<PageProps>().props;
    const [deferredPrompt, setDeferredPrompt] =
        useState<BeforeInstallPromptEvent | null>(null);
    const [showIosInstructions, setShowIosInstructions] = useState(false);
    // Lazy initializer, not an effect: whether the app is ALREADY running
    // standalone is knowable synchronously on first render — setting it
    // from inside an effect would cause an avoidable extra render.
    const [installed, setInstalled] = useState(() => isStandalone());

    const authenticated =
        surface === 'portal' ? Boolean(auth.user) : Boolean(auth.admin);
    const onMatchingSurface = subdomain.surface === surface;

    useEffect(() => {
        const onBeforeInstallPrompt = (event: Event) => {
            event.preventDefault();
            setDeferredPrompt(event as BeforeInstallPromptEvent);
        };
        const onInstalled = () => {
            setInstalled(true);
            setDeferredPrompt(null);
        };

        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener(
                'beforeinstallprompt',
                onBeforeInstallPrompt,
            );
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    if (!onMatchingSurface || !authenticated || installed) {
        return null;
    }

    if (deferredPrompt) {
        return (
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={async () => {
                    await deferredPrompt.prompt();
                    await deferredPrompt.userChoice;
                    setDeferredPrompt(null);
                }}
            >
                <Download className="h-4 w-4" aria-hidden="true" />
                Install app
            </Button>
        );
    }

    if (isIos() && !showIosInstructions) {
        return (
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => setShowIosInstructions(true)}
            >
                <Download className="h-4 w-4" aria-hidden="true" />
                Install app
            </Button>
        );
    }

    if (showIosInstructions) {
        return (
            <p className="text-text-secondary text-xs">
                Tap the Share icon, then &ldquo;Add to Home Screen&rdquo;.
            </p>
        );
    }

    return null;
}
