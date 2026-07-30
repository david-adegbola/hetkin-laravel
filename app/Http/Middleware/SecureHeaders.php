<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers — see MIGRATION_PLAN.md's Stage 13 security
 * review (the three non-CSP headers below) and Stage 15's dedicated
 * Content-Security-Policy pass (this class's own earlier docblock
 * explained exactly why CSP was deliberately deferred out of Stage 13:
 * PostHog, Sentry, and Vite's dev-mode HMR client all load from
 * third-party/dynamic origins, and a CSP wrong in any one direction
 * silently breaks a feature rather than failing loud).
 *
 * Nonce-based, not 'unsafe-inline', for script-src: `Vite::useCspNonce()`
 * is Laravel's own first-class mechanism (see Illuminate\Foundation\Vite —
 * every tag @vite/@viteReactRefresh emit already carries a nonce
 * attribute once this is called) and is generated here, before the view
 * renders, specifically so it's ready by the time app.blade.php's own two
 * inline scripts (the Ziggy route table, the PWA bootstrap flag) need to
 * echo the same value via `Vite::cspNonce()`. Applied globally (unlike
 * PreventBackHistoryCache): none of these headers make a public page any
 * less cacheable, so there's no reason to scope this to authenticated
 * routes only.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', $this->buildPolicy($nonce));

        return $response;
    }

    private function buildPolicy(string $nonce): string
    {
        // Sentry's DSN host and the configured PostHog host are the only
        // two third-party origins this app's frontend ever talks to (both
        // read from the same config Sentry/PostHog's own client init
        // reads at build time — see resources/js/lib/sentry.ts and
        // lib/analytics.ts — so what's allow-listed here always matches
        // what the bundle actually requests). Both SDKs ship as part of
        // the Vite bundle (`import` statements, not a `<script src>` tag),
        // so they only ever need connect-src, never script-src.
        $connectSrc = ["'self'"];

        if ($sentryHost = $this->hostOf(config('sentry.dsn'))) {
            $connectSrc[] = 'https://'.$sentryHost;
        }
        if ($posthogHost = $this->hostOf(config('services.posthog.host'))) {
            $connectSrc[] = 'https://'.$posthogHost;
        }

        // Vite's dev server (default localhost:5173, unconfigured in
        // vite.config.js so this app never overrides the default) serves
        // the HMR client and every module script over both http and ws
        // during `npm run dev`. Local-only: a production build never has
        // a `public/hot` file, and this app's own request-time
        // environment check is the same signal Vite's own isRunningHot()
        // uses, not a guess.
        $viteDevOrigins = [];
        if (app()->environment('local')) {
            $viteDevOrigins = [
                'http://localhost:5173', 'http://127.0.0.1:5173',
                'ws://localhost:5173', 'ws://127.0.0.1:5173',
            ];
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$viteDevOrigins],
            // React/Tailwind-driven components rely on inline `style=""`
            // attributes throughout (chart libraries, dynamic width/color
            // values) — nonce-ing every one of those is impractical, and
            // style-only injection is a materially smaller risk class
            // than script injection, which is why this directive alone
            // keeps 'unsafe-inline' rather than going fully nonce-based.
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => [...$connectSrc, ...$viteDevOrigins],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'object-src' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    private function hostOf(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) ?: null;
    }
}
