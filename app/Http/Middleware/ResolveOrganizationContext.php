<?php

namespace App\Http\Middleware;

use App\Domain\Organizations\CurrentOrganization;
use App\Domain\Organizations\Services\OrganizationContextResolver;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The HTTP-layer consumer Stage 3/4's ADR-26/ADR-31 anticipated: resolves
 * the signed-in user's organization (session's remembered active org, or
 * their first membership — App\Domain\Organizations\Services\
 * OrganizationContextResolver, itself a port of requirePortalContext(),
 * src/lib/portal-context.ts), binds it as this request's
 * CurrentOrganization so every BelongsToOrganization-scoped query
 * downstream (controllers, Actions that don't already bind their own —
 * see UploadMeterReadingsAction/AddScheduleBlockAction) resolves
 * correctly, and shares it as an Inertia prop for the portal layout's
 * sidebar (organization name/status), same data PortalShell.tsx receives
 * in v2.
 *
 * A signed-in user with no organization membership at all cannot reach
 * the portal — v2's exact behavior (`if (!organization) redirect("/sign-in")`
 * in requirePortalContext()) — redirected to sign-in rather than shown an
 * error, host-aware for the same reason Stage 2's redirectGuestsTo/
 * redirectUsersTo are (MIGRATION_PLAN.md ADR-21): a dashboard-subdomain
 * visitor must stay on that subdomain's own sign-in, not bounce to the
 * main domain's.
 *
 * Placed after `auth:web` in every portal route's middleware stack (never
 * before) — it needs an authenticated user to resolve an organization for.
 */
class ResolveOrganizationContext
{
    public function __construct(
        private readonly OrganizationContextResolver $resolver,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user('web');

        $organization = $this->resolver->resolve(
            $user,
            $request->session()->get('active_organization_id'),
        );

        if (! $organization) {
            $loginRoute = $request->getHost() === 'dashboard.'.config('app.domain')
                ? 'portal.login'
                : 'login';

            return redirect()->guest(route($loginRoute));
        }

        $this->currentOrganization->set($organization);

        Inertia::share('organization', [
            'id' => $organization->id,
            'name' => $organization->name,
            'status' => $organization->status->value,
            'process_type' => $organization->process_type?->value,
        ]);

        return $next($request);
    }
}
