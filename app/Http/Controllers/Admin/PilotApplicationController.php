<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Organizations\Models\Invitation;
use App\Domain\Organizations\Scopes\OrganizationScope;
use App\Domain\Pilots\Actions\ApprovePilotApplicationAction;
use App\Domain\Pilots\Actions\RejectPilotApplicationAction;
use App\Domain\Pilots\Enums\PilotApplicationStatus;
use App\Domain\Pilots\Models\PilotApplication;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mirrors the Next.js app's src/app/admin/(protected)/pilots/{page.tsx,
 * actions.ts, invite-url.ts}. Sorted by ICP score, same as v2, so the
 * reviewer triages by fit, not by submission order.
 *
 * Routes carry `admin.role:ADMIN,SUPPORT` — matches v2's
 * requireAdminRole(["ADMIN", "SUPPORT"]) on both actions exactly (see
 * App\Http\Middleware\EnsureAdminRole).
 */
class PilotApplicationController extends Controller
{
    public function index(): Response
    {
        $applications = PilotApplication::query()
            ->orderByDesc('icp_score')
            ->orderByDesc('created_at')
            ->with(['organization' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])
            ->get();

        $pendingInvitations = Invitation::withoutGlobalScope(OrganizationScope::class)
            ->whereIn('organization_id', $applications->pluck('organization.id')->filter())
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('organization_id');

        $rows = $applications->map(function (PilotApplication $app) use ($pendingInvitations) {
            $invitation = $app->organization
                ? $pendingInvitations->get($app->organization->id, collect())->first()
                : null;

            return [
                'id' => $app->id,
                'company_name' => $app->company_name,
                'process_type' => $app->process_type->value,
                'process_type_label' => $app->process_type->label(),
                'estimated_annual_spend_eur' => $app->estimated_annual_spend_eur,
                'win_condition' => $app->win_condition,
                'contact_name' => $app->contact_name,
                'contact_email' => $app->contact_email,
                'icp_score' => $app->icp_score,
                'status' => $app->status->value,
                'created_at' => $app->created_at->toIso8601String(),
                'reviewed_at' => $app->reviewed_at?->toIso8601String(),
                'invite_url' => $invitation ? route('invite.show', ['invitation' => $invitation->id]) : null,
            ];
        });

        $openStatuses = [PilotApplicationStatus::Pending->value, PilotApplicationStatus::InReview->value];

        return Inertia::render('Admin/Pilots', [
            'pending' => $rows->whereIn('status', $openStatuses)->values(),
            'decided' => $rows->whereNotIn('status', $openStatuses)->values(),
        ]);
    }

    public function approve(PilotApplication $pilotApplication, ApprovePilotApplicationAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($pilotApplication, $admin);

        return back();
    }

    public function reject(PilotApplication $pilotApplication, RejectPilotApplicationAction $action): RedirectResponse
    {
        /** @var AdminUser $admin */
        $admin = Auth::guard('admin')->user();

        $action->execute($pilotApplication, $admin);

        return back();
    }
}
