import { Head, Link } from '@inertiajs/react';

/**
 * Mirrors the Next.js app's src/app/not-found.tsx — deliberately minimal,
 * no SiteHeader/Footer chrome, matching v2 exactly. Wired as Laravel's
 * Route::fallback() in routes/web.php, the direct equivalent of Next's
 * automatic not-found rendering for any unmatched path.
 */
export default function NotFound() {
    return (
        <main className="flex min-h-screen flex-col items-center justify-center gap-2 p-8 text-center">
            <Head title="Page not found" />
            <h1 className="font-display text-navy-900 text-xl font-semibold">
                Page not found
            </h1>
            <Link
                href={route('home')}
                className="text-accent text-sm underline"
            >
                Back home
            </Link>
        </main>
    );
}
