@php
    // MIGRATION_PLAN.md Stage 14 — the ONE place install eligibility is
    // decided. A manifest link + a controlling Service Worker are the two
    // things that make Chrome/Edge show their native install affordance;
    // hiding a custom React button does nothing once both exist on an
    // unauthenticated page, so gating happens here, server-side, before
    // either is ever emitted — not in JS. Nothing below fires at all for
    // the marketing site or for a signed-out visitor on either portal's
    // sign-in page.
    $pwaSurface = \App\Support\PwaSurface::current(request());
    $pwaAuthenticated = match ($pwaSurface) {
        'portal' => auth('web')->check(),
        'admin' => auth('admin')->check(),
        default => false,
    };
    $pwaScope = $pwaAuthenticated
        ? \App\Support\PwaSurface::scopeFor($pwaSurface, request())
        : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            Static default matches the Next.js app's metadata.title.default
            (src/app/layout.tsx). Inertia's client-side title() resolver
            (resources/js/app.tsx) overwrites this on every navigation with
            the per-page title, applying the "%s · Hetkin" template — this
            tag is only what a crawler or a hard-reload's first paint sees
            before hydration.
        --}}
        <title inertia>Hetkin — Run production when electricity is cheap</title>
        <meta name="description" content="Hetkin compares your production schedule against real electricity prices and shows you exactly which shifts to move. We only get paid when you save.">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        {{-- Fonts are self-hosted via resources/css/fonts.css (imported from app.css) — no external font request. --}}

        @if($pwaAuthenticated)
            <link rel="manifest" href="/manifest-{{ $pwaSurface }}.webmanifest">
            <meta name="theme-color" content="#0f172a">
            <script {{ Illuminate\Support\Facades\Vite::cspNonce() ? 'nonce="'.Illuminate\Support\Facades\Vite::cspNonce().'"' : '' }}>
                // Read by resources/js/app.tsx, which calls
                // registerServiceWorker() (resources/js/lib/pwa.ts) once its
                // own module code runs. Deliberately just deposits data here
                // rather than calling into app.tsx directly: this plain
                // inline script executes synchronously at parse time, before
                // the deferred `type="module"` Vite bundle below has run —
                // calling a not-yet-defined window function here would throw.
                window.__PWA__ = {
                    surface: @json($pwaSurface),
                    scope: @json($pwaScope),
                };
            </script>
        @endif

        {{--
            Not the plain @routes directive: Ziggy's Blade::directive
            registration (vendor/tightenco/ziggy/src/ZiggyServiceProvider.php)
            only ever forwards a single `$group` argument to
            BladeRouteGenerator::generate(), silently dropping its own
            `$nonce` parameter — so @routes alone would emit this
            <script> tag with no nonce, and App\Http\Middleware\
            SecureHeaders's nonce-based script-src would block it outright.
            Calling the same generator directly, passing the identical
            nonce Vite::useCspNonce() already generated for this request.
            Note the composer package is `tightenco/ziggy` but its PHP
            namespace is `Tighten\Ziggy` (no "co") — confirmed by reading
            vendor/tightenco/ziggy/src/BladeRouteGenerator.php directly.
        --}}
        {!! app(Tighten\Ziggy\BladeRouteGenerator::class)->generate(nonce: Illuminate\Support\Facades\Vite::cspNonce()) !!}
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
