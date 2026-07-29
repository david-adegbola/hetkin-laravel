import { Button } from '@/Components/ui/Button';
import { usePwaUpdate } from '@/lib/pwa';
import { RefreshCw } from 'lucide-react';

/**
 * MIGRATION_PLAN.md Stage 14. Reads `window.__PWA__.scope` — the exact
 * same value resources/views/app.blade.php deposited (and gated on
 * authentication) for the Service Worker registration itself, rather than
 * recomputing a surface/scope of its own; there is nothing here to drift
 * out of sync with that gate. Renders nothing at all until a new Service
 * Worker version is genuinely waiting — never auto-reloads; only ever
 * updates on the visitor's own explicit click, so an in-progress form or
 * upload is never interrupted out from under them.
 */
export function PwaUpdateBanner() {
    const scope = window.__PWA__?.scope ?? null;
    const { updateAvailable, applyUpdate } = usePwaUpdate(scope);

    if (!updateAvailable) {
        return null;
    }

    return (
        <div className="border-border bg-surface flex items-center justify-between gap-3 border-t px-4 py-2 text-sm">
            <span className="text-text-secondary">
                A new version of Hetkin is available.
            </span>
            <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={applyUpdate}
            >
                <RefreshCw className="h-4 w-4" aria-hidden="true" />
                Refresh now
            </Button>
        </div>
    );
}
