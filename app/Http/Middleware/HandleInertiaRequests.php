<?php

namespace App\Http\Middleware;

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
            'subdomain' => [
                'isDashboardHost' => $request->getHost() === 'dashboard.'.config('app.domain'),
                'isAdminHost' => $request->getHost() === 'admin.'.config('app.domain'),
            ],
        ];
    }
}
