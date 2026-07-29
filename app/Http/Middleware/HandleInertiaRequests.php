<?php

namespace App\Http\Middleware;

use App\Support\PwaSurface;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * `auth.user` (customer realm) and `auth.admin` (admin realm) are kept
     * as separate, unambiguous keys rather than a single `auth.user` that
     * could silently be either model depending on which route rendered the
     * page — the two realms are isolated (MIGRATION_PLAN.md ADR-4) and a
     * shared prop should never blur that.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user('web'),
                'admin' => $request->user('admin'),
            ],
            // Lets React build subdomain-correct hrefs without hardcoding a
            // "/dashboard" or "/admin" prefix — see resources/js/lib/
            // subdomain.ts and MIGRATION_PLAN.md ADR-21. Computed here
            // (server-side, from the current request's Host header) rather
            // than read from window.location client-side, since Inertia has
            // no Server/Client component split for this to live in the way
            // v2's subdomain-server.ts vs subdomain-client.ts did.
            //
            // `surface` (Stage 14) is which of the three app surfaces this
            // request belongs to — 'marketing' | 'portal' | 'admin',
            // computed by App\Support\PwaSurface from BOTH the host check
            // above and the /dashboard, /admin path-prefix fallback, since
            // isDashboardHost/isAdminHost alone can't tell "on the main
            // domain viewing /dashboard/*" apart from "on the main domain
            // viewing the marketing site". Used by the PWA install button
            // to know which portal it's rendering inside.
            'subdomain' => [
                'isDashboardHost' => PwaSurface::isPortalHost($request),
                'isAdminHost' => PwaSurface::isAdminHost($request),
                'surface' => PwaSurface::current($request),
            ],
            // Stage 14 — read by resources/js/lib/pwa.ts's update-detection
            // hook to compare against a freshly-fetched manifest response
            // and by the update-available banner's copy. See
            // config/app.php's `version` key for how this is derived and
            // why the two sides can't drift.
            'appVersion' => config('app.version'),
            // One-time flash data. `created_admin` is App\Http\Controllers\
            // Admin\TeamController::store()'s temp-password display, which
            // mirrors v2's CreateAdminUserForm.tsx showing the password
            // exactly once from the Server Action's direct return value.
            // `backfill_result` is App\Http\Controllers\Admin\
            // JobController::triggerBackfillPrices()'s success/error
            // message, mirroring v2's BackfillPricesForm.tsx's
            // useActionState result. Laravel's session flash bag naturally
            // ages both out after the one request that reads them, so no
            // explicit clearing is needed.
            'flash' => [
                'created_admin' => $request->session()->get('created_admin'),
                'backfill_result' => $request->session()->get('backfill_result'),
            ],
        ];
    }
}
