<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers with no third-party-integration risk — see
 * MIGRATION_PLAN.md's Stage 13 security review. Deliberately does NOT
 * include Content-Security-Policy: this app loads PostHog, Sentry, and
 * (in local dev) Vite's HMR client from third-party/dynamic origins, and
 * a CSP wrong in any one of those directions silently breaks a feature
 * rather than failing loud — it needs its own dedicated pass with live
 * browser verification against every integration, not a same-pass
 * addition alongside unrelated headers. Applied globally (unlike
 * PreventBackHistoryCache): none of these three headers make a public
 * page any less cacheable, so there's no reason to scope this to
 * authenticated routes only.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
