<?php

namespace App\Http\Controllers;

use App\Support\PwaSurface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves the two portal-specific PWA identities (MIGRATION_PLAN.md
 * Stage 14) — the customer portal and the admin console each install as
 * their own standalone app, never as one PWA with two routes. Both the
 * manifest and the Service Worker are routed through Laravel rather than
 * served as static public/ files: the manifest's start_url/scope/id must
 * vary by host (real subdomain vs. main-domain path-prefix fallback — see
 * App\Support\PwaSurface's docblock), and the Service Worker needs an
 * explicit no-cache response header that a static-file server's defaults
 * can't be relied on to set.
 */
class PwaController extends Controller
{
    /**
     * @param  'portal'|'admin'  $surface
     */
    public function manifest(Request $request, string $surface): JsonResponse
    {
        $startUrl = PwaSurface::startUrlFor($surface, $request);
        $scope = PwaSurface::scopeFor($surface, $request);

        $manifest = [
            'id' => $scope,
            'name' => $surface === 'portal' ? 'Hetkin' : 'Hetkin Admin',
            'short_name' => $surface === 'portal' ? 'Hetkin' : 'Hetkin Admin',
            'description' => $surface === 'portal'
                ? 'Run production when electricity is cheap.'
                : 'Hetkin internal admin console.',
            'start_url' => $startUrl,
            'scope' => $scope,
            'display' => 'standalone',
            'background_color' => '#f8fafc',
            'theme_color' => '#0f172a',
            'icons' => [
                [
                    'src' => '/icons/icon-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => '/icons/icon-maskable-192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/icons/icon-maskable-512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * @param  'portal'|'admin'  $surface
     */
    public function serviceWorker(string $surface): Response
    {
        $path = public_path("sw-{$surface}.js");

        abort_unless(is_file($path), SymfonyResponse::HTTP_NOT_FOUND);

        return response(file_get_contents($path))
            ->header('Content-Type', 'application/javascript')
            // Not cached at all — otherwise the browser's own ~24h byte-
            // comparison update check can be satisfied from a stale HTTP
            // cache instead of ever reaching the network, silently delaying
            // update detection past what resources/js/lib/pwa.ts's polling
            // assumes.
            ->header('Cache-Control', 'no-cache');
    }
}
