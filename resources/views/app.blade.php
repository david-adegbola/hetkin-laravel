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

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
